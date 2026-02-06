<?php

declare(strict_types=1);

namespace KalprajSolutions\LaravelOnedriveFilesystem;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\Filesystem;

class OneDrive 
{
    protected Filesystem $filesystem;

    protected Client $httpClient;

    /**
     * Create a new OneDrive instance.
     */
    public function __construct(
        protected string $clientId,
        protected string $tenantId,
        protected string $clientSecret,
        protected string $userId,
        protected ?string $basePath = null
    ) {
        $this->httpClient = new Client([
            'base_uri' => 'https://login.microsoftonline.com',
        ]);

        $accessToken = $this->getAccessToken();

        $adapter = new OneDriveAdapter($accessToken, $this->userId, $this->basePath);

        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Get a cached access token or fetch a new one.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'graph_access_token' . md5($this->clientId . $this->tenantId);

        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['expires_at']) && !$cached['expires_at']->isPast()) {
            return $cached['access_token'];
        }

        $token = $this->fetchNewAccessToken();

        Cache::put($cacheKey, $token, now()->addSeconds($token['expires_in'] - 60));

        return $token['access_token'];
    }

    /**
     * Fetch a new access token from Microsoft Graph.
     */
    protected function fetchNewAccessToken(): array
    {
        $response = $this->httpClient->post("/{$this->tenantId}/oauth2/v2.0/token", [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 3599,
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3599),
        ];
    }

    /**
     * Get the underlying filesystem instance.
     */
    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * Dynamically proxy method calls to the filesystem.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->filesystem->$method(...$arguments);
    }
}
