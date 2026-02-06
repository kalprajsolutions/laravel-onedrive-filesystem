<?php

declare(strict_types=1);

namespace KalprajSolutions\LaravelOnedriveFilesystem;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class OneDriveServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/onedrive.php', 'onedrive');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/onedrive.php' => config_path('onedrive.php'),
        ], 'onedrive-config');

        Storage::extend('onedrive', function ($app, $config) {
            $clientId = $config['client_id'] ?? $config['clientId'];
            $tenantId = $config['tenant_id'] ?? $config['tenantId'];
            $clientSecret = $config['client_secret'] ?? $config['clientSecret'];
            $userId = $config['user_id'] ?? $config['userId'];
            $basePath = $config['base_path'] ?? $config['basePath'] ?? null;

            // Create OneDrive instance for token management
            $onedrive = new OneDrive(
                clientId: $clientId,
                tenantId: $tenantId,
                clientSecret: $clientSecret,
                userId: $userId,
                basePath: $basePath
            );

            // Create OneDriveAdapter with the access token from OneDrive
            $adapter = new OneDriveAdapter(
                accessToken: $onedrive->getAccessToken(),
                userId: $userId,
                basePath: $basePath
            );

            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
