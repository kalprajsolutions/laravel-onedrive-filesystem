<?php

declare(strict_types=1);

namespace KalprajSolutions\LaravelOnedriveFilesystem;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class OneDriveAdapter implements FilesystemAdapter
{
    private Client $client;

    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    private PathPrefixer $prefixer;

    /**
     * Microsoft Graph's simple "PUT .../content" endpoint only supports
     * files up to 4 MiB. Anything larger MUST go through an upload session.
     *
     * @see https://learn.microsoft.com/en-us/graph/api/driveitem-put-content
     */
    private const SIMPLE_UPLOAD_MAX_BYTES = 4 * 1024 * 1024;

    /**
     * Each upload-session fragment (except the last) must be a multiple of
     * 320 KiB, and the docs warn you may be throttled above 60 MiB per
     * request. 10 MiB is a safe multiple of 320 KiB well under that ceiling.
     *
     * @see https://learn.microsoft.com/en-us/graph/api/driveitem-createuploadsession
     */
    private const CHUNK_SIZE = 10 * 1024 * 1024;

    private const MAX_CHUNK_RETRIES = 5;

    /**
     * Create a new OneDrive adapter instance.
     */
    public function __construct(
        protected string $accessToken,
        protected string $userId,
        protected ?string $basePath = null
    ) {
        $this->client = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ]);

        $this->prefixer = new PathPrefixer($this->basePath ?? '');
    }

    /**
     * Get the API URL for a path.
     */
    private function getApiUrl(string $path): string
    {
        $path = trim($this->prefixer->prefixPath($path), '/');

        return empty($path)
            ? "{$this->baseUrl}/users/{$this->userId}/drive/root"
            : "{$this->baseUrl}/users/{$this->userId}/drive/root:/{$path}";
    }

    /**
     * Get the children URL for listing.
     */
    private function getChildrenUrl(string $path): string
    {
        $path = trim($this->prefixer->prefixPath($path), '/');

        return empty($path)
            ? "{$this->baseUrl}/users/{$this->userId}/drive/root/children"
            : "{$this->baseUrl}/users/{$this->userId}/drive/root:/{$path}:/children";
    }

    /**
     * Get the items URL by ID.
     */
    private function getItemUrl(string $itemId): string
    {
        return "{$this->baseUrl}/users/{$this->userId}/drive/items/{$itemId}";
    }

    /**
     * Make a request to the Graph API.
     */
    private function request(string $method, string $url, array $options = []): array
    {
        try {
            $options['http_errors'] = false;

            if (isset($options['body']) && is_array($options['body'])) {
                $options['json'] = $options['body'];
                unset($options['body']);
            }

            $response = $this->client->request($method, $url, $options);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                throw new \RuntimeException('API request failed: ' . $response->getBody()->getContents());
            }

            $content = $response->getBody()->getContents();

            return empty($content) ? [] : json_decode($content, true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a file exists.
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->getMetadata($path);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a directory exists.
     */
    public function directoryExists(string $path): bool
    {
        try {
            $metadata = $this->getMetadata($path);

            return isset($metadata['folder']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $stream = \GuzzleHttp\Psr7\Utils::streamFor($contents);

        $this->uploadStream($path, $stream, strlen($contents));
    }

    /**
     * Write a stream to a file.
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        if (! is_resource($contents) && ! $contents instanceof StreamInterface) {
            throw UnableToWriteFile::atLocation($path, 'Invalid stream supplied.');
        }

        $stream = $contents instanceof StreamInterface
            ? $contents
            : \GuzzleHttp\Psr7\Utils::streamFor($contents);

        $size = $stream->getSize();

        if ($size === null) {
            throw UnableToWriteFile::atLocation(
                $path,
                'Unable to determine stream size.'
            );
        }

        $this->uploadStream($path, $stream, $size);
    }

    /**
     * Read a file.
     */
    public function read(string $path): string
    {
        try {
            $url = $this->getApiUrl($path) . ':/content';

            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Failed to read file: ' . $response->getBody()->getContents());
            }

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Read a file as a stream.
     *
     * Streams the HTTP response body directly instead of buffering the
     * whole file into memory first, which matters once files get into the
     * hundreds of MB.
     */
    public function readStream(string $path)
    {
        try {
            $url = $this->getApiUrl($path) . ':/content';

            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'http_errors' => false,
                'stream' => true,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Failed to read file: ' . $response->getBody()->getContents());
            }

            return StreamWrapper::getResource($response->getBody());
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): void
    {
        try {
            $url = $this->getApiUrl($path);

            $response = $this->client->delete($url, ['http_errors' => false]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToDeleteFile::atLocation($path, 'Failed to delete: ' . $response->getBody()->getContents());
            }
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Delete a directory.
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $url = $this->getApiUrl($path);

            $response = $this->client->delete($url, ['http_errors' => false]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToDeleteDirectory::atLocation($path, 'Failed to delete directory: ' . $response->getBody()->getContents());
            }
        } catch (\Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Create a directory.
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $parts = explode('/', trim($this->prefixer->prefixPath($path), '/'));
            $folderName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $url = empty($parentPath)
                ? "{$this->baseUrl}/users/{$this->userId}/drive/root/children"
                : "{$this->baseUrl}/users/{$this->userId}/drive/root:/{$parentPath}:/children";

            $response = $this->client->post($url, [
                'json' => [
                    'name' => $folderName,
                    'folder' => new \stdClass,
                    '@microsoft.graph.conflictBehavior' => 'fail',
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToCreateDirectory::dueToFailure($path, new \RuntimeException('Failed to create directory: ' . $response->getBody()->getContents()));
            }
        } catch (\Throwable $e) {
            throw UnableToCreateDirectory::dueToFailure($path, $e);
        }
    }

    /**
     * List directory contents.
     */
    public function listContents(string $path, bool $deep = false): iterable
    {
        try {
            $url = $this->getChildrenUrl($path);

            $response = $this->client->get($url, ['http_errors' => false]);

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $items = json_decode($response->getBody()->getContents(), true);
            $values = $items['value'] ?? [];

            $basePath = trim($path, '/');

            foreach ($values as $item) {
                $name = $item['name'] ?? '';
                $itemPath = $basePath === '' ? $name : $basePath . '/' . $name;

                if (isset($item['folder'])) {
                    yield new DirectoryAttributes(
                        $itemPath,
                        null,
                        isset($item['lastModifiedDateTime'])
                            ? strtotime($item['lastModifiedDateTime'])
                            : null
                    );

                    if ($deep) {
                        yield from $this->listContents($itemPath, true);
                    }
                } else {
                    yield new FileAttributes(
                        $itemPath,
                        $item['size'] ?? null,
                        null,
                        isset($item['lastModifiedDateTime'])
                            ? strtotime($item['lastModifiedDateTime'])
                            : null,
                        $item['file']['mimeType'] ?? null
                    );
                }
            }
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * Move a file.
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * Copy a file.
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $destPath = trim($this->prefixer->prefixPath($destination), '/');

            $parts = explode('/', $destPath);
            $newName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $parentReference = empty($parentPath)
                ? ['path' => "/users/{$this->userId}/drive/root"]
                : ['path' => "/users/{$this->userId}/drive/root/{$parentPath}"];

            $url = $this->getApiUrl($source) . ':/copy';

            $response = $this->client->post($url, [
                'json' => [
                    'parentReference' => $parentReference,
                    'name' => $newName,
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToCopyFile::fromLocationTo($source, $destination, new \RuntimeException('Failed to copy: ' . $response->getBody()->getContents()));
            }
        } catch (\Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * Get last modified timestamp.
     */
    public function lastModified(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);
        $timestamp = isset($metadata['lastModifiedDateTime'])
            ? strtotime($metadata['lastModifiedDateTime'])
            : null;

        return new FileAttributes($path, null, null, $timestamp);
    }

    /**
     * Get file size.
     */
    public function fileSize(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        return new FileAttributes($path, $metadata['size'] ?? null);
    }

    /**
     * Get MIME type.
     */
    public function mimeType(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        return new FileAttributes(
            $path,
            $metadata['size'] ?? null,
            null,
            $metadata['lastModifiedDateTime'] ?? null,
            $metadata['file']['mimeType'] ?? null
        );
    }

    /**
     * Get metadata for an item.
     */
    public function getMetadata(string $path): array
    {
        try {
            $url = $this->getApiUrl($path);

            $response = $this->client->get($url, ['http_errors' => false]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToRetrieveMetadata::create($path, 'metadata', 'Failed to get metadata');
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $e->getMessage(), $e);
        }
    }

    /**
     * Create an upload session for large files.
     */
    public function createUploadSession(string $path): string
    {
        $url = $this->getApiUrl($path) . ':/createUploadSession';

        $response = $this->client->post($url, [
            'json' => [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'replace',
                ],
            ],
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(
                'Failed to create upload session: ' . $response->getBody()->getContents()
            );
        }

        $json = json_decode((string) $response->getBody(), true);

        if (! isset($json['uploadUrl'])) {
            throw new \RuntimeException(
                'Upload session URL not returned: ' . json_encode($json)
            );
        }

        return $json['uploadUrl'];
    }

    /**
     * Route a write to either the simple "small file" endpoint or a
     * resumable upload session, depending on size.
     */
    private function uploadStream(
        string $path,
        StreamInterface $stream,
        int $size
    ): void {
        if ($size <= self::SIMPLE_UPLOAD_MAX_BYTES) {
            $this->simpleUpload($path, $stream);

            return;
        }

        $this->chunkedUpload($path, $stream, $size);
    }

    /**
     * Upload a small file (<= 4 MiB) in a single request.
     */
    private function simpleUpload(string $path, StreamInterface $stream): void
    {
        try {
            $response = $this->client->put(
                $this->getApiUrl($path) . ':/content',
                [
                    'body' => $stream,
                    'headers' => [
                        'Content-Type' => 'application/octet-stream',
                    ],
                    'http_errors' => false,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException($response->getBody()->getContents());
            }
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Upload a large file via a resumable upload session, in fragments
     * that are exact multiples of 320 KiB (except the final fragment),
     * with retries for transient failures.
     */
    private function chunkedUpload(string $path, StreamInterface $stream, int $size): void
    {
        // A 300 MB+ upload over a slow link can easily exceed PHP's default
        // max_execution_time when run inside a normal web request/job.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $uploadUrl = $this->createUploadSession($path);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, 'Failed to start upload session: ' . $e->getMessage(), $e);
        }

        // The upload-session URL is pre-authenticated by Microsoft Graph;
        // it must NOT receive the Authorization header.
        $client = new Client([
            'connect_timeout' => 15,
            'timeout' => 0,
        ]);

        $offset = 0;

        while ($offset < $size) {
            $length = min(self::CHUNK_SIZE, $size - $offset);
            $chunk = $this->readExactly($stream, $length);
            $actualLength = strlen($chunk);

            if ($actualLength === 0) {
                throw UnableToWriteFile::atLocation(
                    $path,
                    'Stream ended unexpectedly after ' . $offset . ' of ' . $size . ' bytes.'
                );
            }

            $end = $offset + $actualLength - 1;

            $this->putChunkWithRetries($client, $uploadUrl, $chunk, $offset, $end, $size, $path);

            $offset += $actualLength;
        }
    }

    /**
     * Read exactly $length bytes from the stream (or until EOF), looping
     * over short reads so non-final fragments stay aligned to the size
     * Microsoft Graph expects.
     */
    private function readExactly(StreamInterface $stream, int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length && ! $stream->eof()) {
            $piece = $stream->read($length - strlen($buffer));

            if ($piece === '') {
                // Stream stalled without EOF; avoid spinning forever.
                break;
            }

            $buffer .= $piece;
        }

        return $buffer;
    }

    /**
     * PUT a single fragment, retrying with backoff on transient failures
     * (network errors, 429 throttling, 5xx) before giving up.
     */
    private function putChunkWithRetries(
        Client $client,
        string $uploadUrl,
        string $chunk,
        int $offset,
        int $end,
        int $size,
        string $path
    ): void {
        $attempt = 0;

        while (true) {
            $response = null;
            $errorMessage = null;

            try {
                $response = $client->put($uploadUrl, [
                    'headers' => [
                        'Content-Length' => strlen($chunk),
                        'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                    ],
                    'body' => $chunk,
                    'http_errors' => false,
                ]);
            } catch (GuzzleException $e) {
                $errorMessage = $e->getMessage();
            }

            if ($response !== null && in_array($response->getStatusCode(), [200, 201, 202], true)) {
                return;
            }

            $attempt++;

            if ($attempt > self::MAX_CHUNK_RETRIES) {
                $message = $response !== null
                    ? "Chunk upload failed with status {$response->getStatusCode()}: " . $response->getBody()->getContents()
                    : 'Chunk upload failed: ' . $errorMessage;

                throw UnableToWriteFile::atLocation($path, $message);
            }

            $this->waitBeforeRetry($response, $attempt);
        }
    }

    /**
     * Honor Microsoft's Retry-After header when present, otherwise back
     * off linearly.
     */
    private function waitBeforeRetry(?ResponseInterface $response, int $attempt): void
    {
        $retryAfter = $response?->getHeaderLine('Retry-After');
        $seconds = is_numeric($retryAfter) ? (int) $retryAfter : min(30, $attempt * 2);

        sleep(max(1, $seconds));
    }

    /**
     * Get a sharing URL for a file.
     */
    public function getUrl(string $path): string
    {
        try {
            $metadata = $this->getMetadata($path);

            if (isset($metadata['id'])) {
                $url = $this->getItemUrl($metadata['id']) . '/createLink';

                $response = $this->client->post($url, [
                    'json' => [
                        'type' => 'view',
                        'scope' => 'anonymous',
                    ],
                    'http_errors' => false,
                ]);

                if ($response->getStatusCode() < 400) {
                    $result = json_decode($response->getBody()->getContents(), true);

                    return $result['link']['webUrl'] ?? '';
                }
            }

            // Fallback: construct URL from path
            return "https://onedrive.live.com/view.aspx?resid={$this->userId}&path={$path}";
        } catch (\Throwable) {
            throw UnableToRetrieveMetadata::create($path, 'url', 'Failed to get sharing URL');
        }
    }

    /**
     * Set visibility (not supported by OneDrive).
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'OneDrive does not support visibility settings.');
    }

    /**
     * Get visibility (not supported by OneDrive).
     */
    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'OneDrive does not support visibility settings.');
    }
}
