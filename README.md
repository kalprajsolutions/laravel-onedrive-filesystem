<div align="left">
    <a href="https://kalprajsolutions.com/?utm_source=github&utm_medium=banner&utm_campaign=laravel-onedrive-filesystem">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://github.com/Pushkraj19/Pushkraj19/blob/d72ff5e2eb7299546cd8348c25fd835a39becce0/laravel-onedrive-filesystem.png">
        <img alt="Logo for Laravel Onedrive Filesystem" src="https://github.com/Pushkraj19/Pushkraj19/blob/d72ff5e2eb7299546cd8348c25fd835a39becce0/laravel-onedrive-filesystem.png">
      </picture>
    </a>
</div>

<p align="center">
  <a href="https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem"><img src="https://img.shields.io/packagist/v/kalprajsolutions/laravel-onedrive-filesystem.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem"><img src="https://img.shields.io/packagist/dt/kalprajsolutions/laravel-onedrive-filesystem.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem"><img src="https://img.shields.io/packagist/l/kalprajsolutions/laravel-onedrive-filesystem.svg?style=flat-square" alt="License"></a>
  <a href="https://packagist.org/packages/kalprajsolutions/laravel-onedrive-filesystem"><img src="https://img.shields.io/packagist/php-v/kalprajsolutions/laravel-onedrive-filesystem.svg?style=flat-square" alt="PHP Version"></a>
</p>

# Laravel OneDrive Filesystem

Use **Microsoft OneDrive for Business** as a native Laravel filesystem disk. This package provides a drop-in OneDrive filesystem driver powered by the **Microsoft Graph API** with automatic token caching — no OAuth redirects, no manual token refresh, no boilerplate.

Works with `Storage::disk('onedrive')` exactly like local or S3 storage. Upload files, download files, manage directories, generate sharing URLs — all through Laravel's standard Storage facade.

**Laravel 10, 11, 12, and 13** supported. **PHP 8.1+** required.

---

## Why This Package?

There are other OneDrive adapters for Laravel, but most of them share the same problems:

- They require you to manually handle OAuth redirects and store access tokens yourself.
- They give you a Flysystem adapter you have to wire up with `Storage::build()` instead of the familiar `Storage::disk()`.
- They don't cache tokens, so every request hits Azure AD for a new token.
- They only support one account at a time.

This package fixes all of that. It registers as a real Laravel filesystem driver, caches tokens using Laravel's cache system, and supports multiple OneDrive accounts through multiple disk configurations. You install it, add your Azure AD credentials to `.env`, and start using `Storage::disk('onedrive')` — the same way you'd use any other Laravel storage disk.

### How It Compares

| Feature | This Package | `justus/flysystem-onedrive` | `sahablibya/laravel-sharepoint-filesystem` | `LLoadout/microsoftgraph` |
|---|:---:|:---:|:---:|:---:|
| Native `Storage::disk('onedrive')` | ✅ | ❌ (requires `Storage::build()`) | ✅ | ✅ |
| Client credentials auth (no OAuth redirect) | ✅ | ❌ (manual token required) | ✅ | ❌ |
| Automatic token caching | ✅ | ❌ | ✅ | ❌ |
| Multiple OneDrive accounts | ✅ | ❌ | ❌ | ❌ |
| Laravel 10 support | ✅ | ❌ | ✅ | ✅ |
| Laravel 12 support | ✅ | ✅ | ✅ | ✅ |
| Laravel 13 support | ✅ | — | — | — |
| Scoped base path (`GRAPH_BASE_PATH`) | ✅ | ❌ | ❌ | ❌ |
| Pure OneDrive focus (no bloat) | ✅ | ✅ | ❌ (SharePoint included) | ❌ (Mail, Teams, Excel included) |

A dash means we have not verified that version against the package in question, not that it fails.

---

## Who Is This For?

- You're building a **Laravel app on a corporate Microsoft 365 tenant** and want to store files on OneDrive for Business instead of (or alongside) S3 or local storage.
- You need a filesystem disk for **Spatie Laravel Backup** that lands on OneDrive.
- You want to give users file storage backed by OneDrive without building a custom OAuth flow.
- You're running **automated jobs, cron commands, or queues** that need to read/write OneDrive files without a browser-based login.

---

## Installation

Install via Composer:

```bash
composer require kalprajsolutions/laravel-onedrive-filesystem
```

### Installing the Laravel 13 branch

Packagist still serves the release constrained to Laravel 12, so on Laravel 13
you need to point Composer at this fork until the change is released upstream.
Add the repository to your application's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/ValleyInv/laravel-onedrive-filesystem"
    }
]
```

Then require the branch:

```bash
composer require kalprajsolutions/laravel-onedrive-filesystem:dev-laravel-13-support
```

Drop the `repositories` entry and go back to a normal version constraint once
upstream ships a release supporting Laravel 13.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=onedrive-config
```

This creates `config/onedrive.php` in your application.

---

## Configuration

### Environment Variables

Add these to your `.env` file:

```dotenv
GRAPH_CLIENT_ID=your-client-id
GRAPH_TENANT_ID=your-tenant-id
GRAPH_CLIENT_SECRET=your-client-secret
GRAPH_USER_ID=user@yourdomain.com
GRAPH_BASE_PATH=
```

### Filesystem Disk

Add the OneDrive disk to `config/filesystems.php`:

```php
'disks' => [
    // ...

    'onedrive' => [
        'driver'       => 'onedrive',
        'client_id'    => env('GRAPH_CLIENT_ID'),
        'tenant_id'    => env('GRAPH_TENANT_ID'),
        'client_secret'=> env('GRAPH_CLIENT_SECRET'),
        'user_id'      => env('GRAPH_USER_ID'),
        'base_path'    => env('GRAPH_BASE_PATH'),
    ],
],
```

### Multiple Accounts

You can configure multiple OneDrive disks for different accounts:

```php
'disks' => [
    'onedrive' => [
        'driver'       => 'onedrive',
        'client_id'    => env('GRAPH_CLIENT_ID'),
        'tenant_id'    => env('GRAPH_TENANT_ID'),
        'client_secret'=> env('GRAPH_CLIENT_SECRET'),
        'user_id'      => env('GRAPH_USER_ID'),
        'base_path'    => env('GRAPH_BASE_PATH'),
    ],
    'onedrive_backup' => [
        'driver'       => 'onedrive',
        'client_id'    => env('BACKUP_GRAPH_CLIENT_ID'),
        'tenant_id'    => env('BACKUP_GRAPH_TENANT_ID'),
        'client_secret'=> env('BACKUP_GRAPH_CLIENT_SECRET'),
        'user_id'      => env('BACKUP_GRAPH_USER_ID'),
        'base_path'    => env('BACKUP_GRAPH_BASE_PATH'),
    ],
],
```

---

## Azure AD App Registration

You need an Azure AD app registration with **application permissions** (not delegated). This is what allows the package to authenticate without a user login flow.

### Step 1: Register the App

1. Go to [Azure Portal](https://portal.azure.com) → **Azure Active Directory** → **App registrations** → **New registration**
2. Name: your app name
3. Supported account types: **Accounts in this organizational directory only** (Single tenant)
4. Redirect URI: leave blank
5. Click **Register**

### Step 2: Collect Credentials

After registration, copy these values:
- **Application (client) ID** → `GRAPH_CLIENT_ID`
- **Directory (tenant) ID** → `GRAPH_TENANT_ID`

### Step 3: Create a Client Secret

1. Go to **Certificates & secrets** → **New client secret**
2. Add a description, pick an expiration, click **Add**
3. Copy the **secret value** immediately (it won't be shown again) → `GRAPH_CLIENT_SECRET`

### Step 4: Add API Permissions

1. Go to **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions**
2. Add: `Files.ReadWrite.All`
3. Click **Grant admin consent** (requires admin role)

### Step 5: Get the User ID

1. Go to [Graph Explorer](https://developer.microsoft.com/en-us/graph/graph-explorer)
2. Sign in and run the "my profile" query
3. Copy the `id` from the response → `GRAPH_USER_ID`

---

## Usage

All standard Laravel filesystem methods work. Here are the most common operations:

### Write and Read Files

```php
use Illuminate\Support\Facades\Storage;

// Write a file
Storage::disk('onedrive')->put('documents/report.txt', 'Report content');

// Read a file
$content = Storage::disk('onedrive')->get('documents/report.txt');
```

### Upload Files from Requests

```php
// Upload with auto-generated filename
Storage::disk('onedrive')->putFile('avatars', $request->file('avatar'));

// Upload with a custom filename
Storage::disk('onedrive')->putFileAs('documents', $request->file('document'), 'custom-name.pdf');
```

### Download Files

```php
// Return a download response
return Storage::disk('onedrive')->download('document.pdf', 'my-document.pdf');

// Get a sharing URL
$url = Storage::disk('onedrive')->getUrl('document.pdf');

// Stream large files
$source = Storage::disk('onedrive')->readStream('large-file.zip');
$dest   = fopen(storage_path('large-file.zip'), 'w');

stream_copy_to_stream($source, $dest);

fclose($source);
fclose($dest);
```

### Check, Copy, Move, Delete

```php
// Check existence
$exists = Storage::disk('onedrive')->exists('filename.txt');

// Copy
Storage::disk('onedrive')->copy('source.txt', 'destination.txt');

// Move / Rename
Storage::disk('onedrive')->move('old-name.txt', 'new-name.txt');

// Delete
Storage::disk('onedrive')->delete('filename.txt');
```

### File Metadata

```php
$size      = Storage::disk('onedrive')->size('filename.txt');
$timestamp = Storage::disk('onedrive')->lastModified('filename.txt');
$mime      = Storage::disk('onedrive')->mimeType('filename.txt');
```

### Directory Operations

```php
// Create a directory
Storage::disk('onedrive')->createDirectory('Documents/NewFolder');

// List files
$files = Storage::disk('onedrive')->files('Documents');

// List files recursively
$allFiles = Storage::disk('onedrive')->allFiles('Documents');

// List directories
$directories = Storage::disk('onedrive')->directories('Documents');

// Delete a directory and its contents
Storage::disk('onedrive')->deleteDirectory('Documents/OldFolder');
```

---

## Available Methods

| Method | Description |
|---|---|
| `put($path, $contents)` | Write content to a file |
| `putFile($path, $file)` | Upload a file |
| `putFileAs($path, $file, $name)` | Upload with a custom name |
| `get($path)` | Read file content |
| `readStream($path)` | Read file as a stream |
| `download($path, $name)` | Download as a response |
| `exists($path)` | Check if file exists |
| `delete($path)` | Delete a file |
| `copy($source, $destination)` | Copy a file |
| `move($source, $destination)` | Move / rename a file |
| `size($path)` | Get file size |
| `lastModified($path)` | Get last modified timestamp |
| `mimeType($path)` | Get MIME type |
| `files($directory)` | List files in a directory |
| `directories($directory)` | List directories |
| `allFiles($directory)` | List all files recursively |
| `allDirectories($directory)` | List all directories recursively |
| `createDirectory($path)` | Create a directory |
| `deleteDirectory($directory)` | Delete a directory |
| `getUrl($path)` | Get a sharing URL |

---

## Spatie Laravel Backup

This package works as a backup destination for [spatie/laravel-backup](https://github.com/spatie/laravel-backup). Configure your backup disk:

```php
// config/backup.php
'destination' => [
    'disks' => ['onedrive'],
],
```

Then run your backup as usual:

```bash
php artisan backup:run
```

---

## Token Caching

Access tokens are automatically cached using Laravel's default cache driver. You don't need to configure anything — the package handles token acquisition and refresh transparently. Make sure your cache driver is properly configured (Redis, Memcached, or database all work fine).

---

## FAQ

### What Laravel versions are supported?

Laravel 10, 11, 12, and 13.

Laravel 13 support is a constraint change only — no source changes were needed.
`OneDriveAdapter` implements Flysystem's `FilesystemAdapter` and touches no
Illuminate classes at all, the service provider uses the `Storage::extend`
pattern that 13.x still documents, and the only framework facade in the package
is `Cache`, which stores an array rather than an object (so Laravel 13's
`serializable_classes` hardening does not apply).

### Can I use this with personal Microsoft accounts?

This package is built for **OneDrive for Business** (Microsoft 365 / organizational accounts). Personal Microsoft accounts (`@outlook.com`, `@hotmail.com`) may require additional configuration and are not officially supported.

### How do I use a scoped base path?

Set `GRAPH_BASE_PATH` in your `.env` to a folder name. All file operations will be relative to that folder inside OneDrive. For example, `GRAPH_BASE_PATH=MyApp` means `Storage::disk('onedrive')->put('file.txt', ...)` writes to `MyApp/file.txt` in OneDrive.

### Does this support large file uploads?

Yes. The adapter uses Microsoft Graph upload sessions for files that exceed the simple upload limit.

```php
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
```

### How do I get support?

Open an issue on [GitHub](https://github.com/kalprajsolutions/laravel-onedrive-filesystem/issues) or contact us through [kalprajsolutions.com](https://kalprajsolutions.com).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

**Unreleased** — Laravel 13 support. Widened the `illuminate/support` and
`illuminate/filesystem` constraints to include `^13.0`. No source changes.

**v1.1** — Added chunk upload support for files larger than 4 MiB via Microsoft Graph upload sessions.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for details. Contributions, bug reports, and feature requests are welcome.

## Security

If you discover a security vulnerability, please open an issue on [GitHub](https://github.com/kalprajsolutions/laravel-onedrive-filesystem/issues) or contact us through [kalprajsolutions.com](https://kalprajsolutions.com).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
