<?php

declare(strict_types=1);

namespace KalprajSolutions\LaravelOnedriveFilesystem;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
use Psr\Http\Message\StreamInterface;

class OneDriveAdapter implements FilesystemAdapter
{
    private Client $client;

    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    private PathPrefixer $prefixer;

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
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
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
                throw new \RuntimeException('API request failed: '.$response->getBody()->getContents());
            }

            $content = $response->getBody()->getContents();

            return empty($content) ? [] : json_decode($content, true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('API request failed: '.$e->getMessage(), 0, $e);
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

    /**
     * Write a file.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $url = $this->getApiUrl($path).':/content';

            $response = $this->client->put($url, [
                'body' => $contents,
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/octet-stream',
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToWriteFile::atLocation($path, 'Failed to write file: '.$response->getBody()->getContents());
            }
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Write a stream to a file.
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $url = $this->getApiUrl($path).':/content';

            $body = $contents instanceof StreamInterface
                ? $contents
                : \GuzzleHttp\Psr7\Utils::streamFor($contents);

            $response = $this->client->put($url, [
                'body' => $body,
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/octet-stream',
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToWriteFile::atLocation($path, 'Failed to write stream: '.$response->getBody()->getContents());
            }
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Read a file.
     */
    public function read(string $path): string
    {
        try {
            $url = $this->getApiUrl($path).':/content';

            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToReadFile::fromLocation($path, 'Failed to read file: '.$response->getBody()->getContents());
            }

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Read a file as a stream.
     */
    public function readStream(string $path)
    {
        try {
            $contents = $this->read($path);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);

            return $stream;
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

            $response = $this->client->delete($url);

            if ($response->getStatusCode() >= 400) {
                throw UnableToDeleteFile::atLocation($path, 'Failed to delete: '.$response->getBody()->getContents());
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

            $response = $this->client->delete($url);

            if ($response->getStatusCode() >= 400) {
                throw UnableToDeleteDirectory::atLocation($path, 'Failed to delete directory: '.$response->getBody()->getContents());
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
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToCreateDirectory::dueToFailure($path, new \RuntimeException('Failed to create directory: '.$response->getBody()->getContents()));
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

            $response = $this->client->get($url);

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $items = json_decode($response->getBody()->getContents(), true);
            $values = $items['value'] ?? [];

            foreach ($values as $item) {
                $itemPath = ltrim($item['name'] ?? '', '/');

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
            $sourcePath = trim($this->prefixer->prefixPath($source), '/');
            $destPath = trim($this->prefixer->prefixPath($destination), '/');

            $parts = explode('/', $destPath);
            $newName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $parentReference = empty($parentPath)
                ? ['path' => "/users/{$this->userId}/drive/root"]
                : ['path' => "/users/{$this->userId}/drive/root/{$parentPath}"];

            $url = $this->getApiUrl($source).':/copy';

            $response = $this->client->post($url, [
                'json' => [
                    'parentReference' => $parentReference,
                    'name' => $newName,
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw UnableToCopyFile::fromLocationTo($source, $destination, new \RuntimeException('Failed to copy: '.$response->getBody()->getContents()));
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

            $response = $this->client->get($url);

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
    public function createUploadSession(string $path, array $metadata = []): string
    {
        try {
            $url = $this->getApiUrl($path).'/createUploadSession';

            $response = $this->client->post($url, [
                'json' => [
                    'item' => [
                        '@microsoft.graph.conflictBehavior' => 'rename',
                        ...$metadata,
                    ],
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Failed to create upload session: '.$response->getBody()->getContents());
            }

            $result = json_decode($response->getBody()->getContents(), true);

            return $result['uploadUrl'] ?? '';
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create upload session: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a sharing URL for a file.
     */
    public function getUrl(string $path): string
    {
        try {
            $metadata = $this->getMetadata($path);

            if (isset($metadata['id'])) {
                $url = $this->getItemUrl($metadata['id']).'/createLink';

                $response = $this->client->post($url, [
                    'json' => [
                        'type' => 'view',
                        'scope' => 'anonymous',
                    ],
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
