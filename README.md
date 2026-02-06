# Laravel OneDrive Filesystem

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kalprajsolutions/laravel-onedrive-filesystem.svg)](https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem)
[![Total Downloads](https://img.shields.io/packagist/dt/kalprajsolutions/laravel-onedrive-filesystem.svg)](https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem)
[![License](https://img.shields.io/packagist/license/kalprajsolutions/laravel-onedrive-filesystem.svg)](https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem)
[![PHP Version](https://img.shields.io/packagist/php-v/kalprajsolutions/laravel-onedrive-filesystem.svg)](https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem)
[![Build Status](https://img.shields.io/travis/kalprajsolutions/laravel-onedrive-filesystem.svg)](https://travis-ci.org/kalprajsolutions/laravel-onedrive-filesystem)
[![StyleCI](https://github.styleci.io/repos/123456789/shield)](https://styleci.io/repos/123456789)

Seamless **OneDrive integration** for Laravel's filesystem. Use **Microsoft Graph API** to store, retrieve, and manage files in **OneDrive for Business**. This package provides a full-featured Laravel filesystem driver that supports all standard Laravel storage operations including file uploads, downloads, directory management, and sharing capabilities.

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Environment Variables](#environment-variables)
- [Azure AD App Registration](#azure-ad-app-registration)
- [Basic Usage](#basic-usage)
- [File Operations](#file-operations)
- [Directory Operations](#directory-operations)
- [Available Methods](#available-methods)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)
- [FAQ](#faq)

## Installation

Install the package via Composer:

```bash
composer require kalprajsolutions/laravel-onedrive-filesystem
```

### Prerequisites

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- Guzzle HTTP client

## Configuration

Publish the configuration file to your application:

```bash
php artisan vendor:publish --tag=onedrive-config
```

This will create a configuration file at `config/onedrive.php`.

## Environment Variables

Add the following environment variables to your `.env` file:

```env
# Microsoft Azure AD Configuration
GRAPH_CLIENT_ID=your-client-id
GRAPH_TENANT_ID=your-tenant-id
GRAPH_CLIENT_SECRET=your-client-secret

# OneDrive User ID (email or object ID)
GRAPH_USER_ID=user@domain.com

# Optional: Base path within OneDrive
GRAPH_BASE_PATH=
```

## Azure AD App Registration

### Create App Registration

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to Azure Active Directory > App registrations
3. Click "New registration"
4. Fill in the details:
   - Name: Your app name
   - Supported account types: Accounts in this organizational directory only (Single tenant)
   - Redirect URI: Leave blank for now
5. Click "Register"

### Note Important Values

After registration, note these values:
- Application (client) ID
- Directory (tenant) ID

### Create Client Secret

1. Go to "Certificates & secrets"
2. Click "New client secret"
3. Add a description and select expiration
4. Click "Add"
5. **Important**: Copy the secret value immediately (it won't be shown again)

### Add API Permissions

1. Go to "API permissions"
2. Click "Add a permission"
3. Select "Microsoft Graph"
4. Select "Application permissions"
5. Add: `Files.ReadWrite.All`
6. Click "Add permissions"
7. Click "Grant admin consent" (if required)

## Basic Usage

### Configure Filesystem Disk

Add the OneDrive disk to your `config/filesystems.php`:

```php
'disks' => [
    // ...

    'onedrive' => [
        'driver' => 'onedrive',
        'client_id' => env('GRAPH_CLIENT_ID'),
        'tenant_id' => env('GRAPH_TENANT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'user_id' => env('GRAPH_USER_ID'),
        'base_path' => env('GRAPH_BASE_PATH'),
    ],
],
```

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Write a file to OneDrive
Storage::disk('onedrive')->put('filename.txt', 'Hello World');

// Read a file from OneDrive
$content = Storage::disk('onedrive')->get('filename.txt');

// Check if file exists in OneDrive
$exists = Storage::disk('onedrive')->exists('filename.txt');

// Delete a file from OneDrive
Storage::disk('onedrive')->delete('filename.txt');
```

## File Operations

### Upload Files

```php
use Illuminate\Support\Facades\Storage;

// Upload a string content
Storage::disk('onedrive')->put('documents/report.txt', 'Report content');

// Upload from uploaded file
Storage::disk('onedrive')->putFile('avatars', $request->file('avatar'));

// Upload with custom filename
Storage::disk('onedrive')->putFileAs('documents', $request->file('document'), 'custom-name.pdf');
```

### Download Files

```php
use Illuminate\Support\Facades\Storage;

// Download as response
return Storage::disk('onedrive')->download('document.pdf', 'my-document.pdf');

// Get file URL for sharing
$url = Storage::disk('onedrive')->getUrl('document.pdf');
```

### File Metadata

```php
use Illuminate\Support\Facades\Storage;

// Get file size
$size = Storage::disk('onedrive')->size('filename.txt');

// Get last modified timestamp
$timestamp = Storage::disk('onedrive')->lastModified('filename.txt');

// Get MIME type
$mime = Storage::disk('onedrive')->mimeType('filename.txt');
```

### File Copy and Move

```php
use Illuminate\Support\Facades\Storage;

// Copy file within OneDrive
Storage::disk('onedrive')->copy('source.txt', 'destination.txt');

// Move/Rename file in OneDrive
Storage::disk('onedrive')->move('old-name.txt', 'new-name.txt');
```

## Directory Operations

### Create Directories

```php
use Illuminate\Support\Facades\Storage;

// Create a directory in OneDrive
Storage::disk('onedrive')->createDirectory('Documents/NewFolder');
```

### List Directory Contents

```php
use Illuminate\Support\Facades\Storage;

// List all files in a directory
$files = Storage::disk('onedrive')->files('Documents');

// List all directories in a path
$directories = Storage::disk('onedrive')->directories('Documents');

// List all files recursively
$allFiles = Storage::disk('onedrive')->allFiles('Documents');

// List all directories recursively
$allDirs = Storage::disk('onedrive')->allDirectories('Documents');

// List all contents with details
$contents = Storage::disk('onedrive')->listContents('Documents');
```

### Delete Directories

```php
use Illuminate\Support\Facades\Storage;

// Delete a directory and its contents
Storage::disk('onedrive')->deleteDirectory('Documents/OldFolder');
```

## Available Methods

The Laravel OneDrive filesystem driver supports all standard Laravel filesystem methods:

| Method | Description |
|--------|-------------|
| `put($path, $contents)` | Write content to a file |
| `putFile($path, $file)` | Upload a file |
| `putFileAs($path, $file, $name)` | Upload with custom name |
| `get($path)` | Read file content |
| `download($path, $name)` | Download file as response |
| `exists($path)` | Check if file exists |
| `delete($path)` | Delete a file |
| `copy($source, $destination)` | Copy file |
| `move($source, $destination)` | Move file |
| `size($path)` | Get file size |
| `lastModified($path)` | Get last modified timestamp |
| `mimeType($path)` | Get MIME type |
| `files($directory)` | List files in directory |
| `directories($directory)` | List directories |
| `allFiles($directory)` | List all files recursively |
| `allDirectories($directory)` | List all directories recursively |
| `createDirectory($path)` | Create directory |
| `deleteDirectory($directory)` | Delete directory |
| `getUrl($path)` | Get sharing URL |

## Testing

Run the test suite:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email dev@kalprajsolutions.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## FAQ

### What versions of Laravel are supported?

This package supports Laravel 10, 11, and 12 with PHP 8.1 or higher.

### How do I obtain Microsoft Graph API credentials?

You need to register an application in Azure Active Directory. Follow the [Azure AD App Registration](#azure-ad-app-registration) section for detailed instructions.

### What permissions does the app need?

The application requires `Files.ReadWrite.All` permission from Microsoft Graph API to read and write files to OneDrive.

### How do I handle token expiration?

This package uses Laravel's default cache mechanism. Tokens are automatically cached and refreshed. Make sure your cache configuration is properly set up.

### Can I use this with personal Microsoft accounts?

This package is designed for OneDrive for Business. For personal Microsoft accounts, additional configuration may be required.

### Where can I get support?

For issues and feature requests, please use the GitHub issue tracker. For general questions, email dev@kalprajsolutions.com.

### Is this package production-ready?

Yes, this package follows Laravel best practices and uses the official Microsoft Graph API for OneDrive integration. However, always test in a staging environment before deploying to production.

### How do I configure a base path?

Set the `GRAPH_BASE_PATH` environment variable to define a base folder within OneDrive. All file operations will be relative to this path.

### Can I use multiple OneDrive accounts?

Yes, you can configure multiple disks in your `config/filesystems.php` with different credentials for each account.

### Does this package support large file uploads?

Yes, the adapter supports creating upload sessions for large files through the Microsoft Graph API.
