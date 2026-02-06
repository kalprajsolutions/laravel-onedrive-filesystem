# Installation Guide

## Prerequisites

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- Guzzle HTTP client

## Installation

### 1. Install the Package

Install the package via Composer:

```bash
composer require kalprajsolutions/laravel-onedrive-filesystem
```

### 2. Publish the Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=onedrive-config
```

This will create a configuration file at `config/onedrive.php`.

### 3. Configure Environment Variables

Add the following to your `.env` file:

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

### 4. Azure AD App Registration

#### Create App Registration

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to Azure Active Directory > App registrations
3. Click "New registration"
4. Fill in the details:
   - Name: Your app name
   - Supported account types: Accounts in this organizational directory only (Single tenant)
   - Redirect URI: Leave blank for now
5. Click "Register"

#### Note Important Values

After registration, note these values:
- Application (client) ID
- Directory (tenant) ID

#### Create Client Secret

1. Go to "Certificates & secrets"
2. Click "New client secret"
3. Add a description and select expiration
4. Click "Add"
5. **Important**: Copy the secret value immediately (it won't be shown again)

#### Add API Permissions

1. Go to "API permissions"
2. Click "Add a permission"
3. Select "Microsoft Graph"
4. Select "Application permissions"
5. Add: `Files.ReadWrite.All`
6. Click "Add permissions"
7. Click "Grant admin consent" (if required)

### 5. Configure Filesystem Disk

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

## Usage Examples

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Write a file
Storage::disk('onedrive')->put('filename.txt', 'Hello World');

// Read a file
$content = Storage::disk('onedrive')->get('filename.txt');

// Check if file exists
$exists = Storage::disk('onedrive')->exists('filename.txt');

// Delete a file
Storage::disk('onedrive')->delete('filename.txt');
```

### Directory Operations

```php
// Create a directory
Storage::disk('onedrive')->createDirectory('Documents/NewFolder');

// List files
$files = Storage::disk('onedrive')->files('Documents');
$directories = Storage::disk('onedrive')->directories('Documents');

// List all contents recursively
$allContents = Storage::disk('onedrive')->allFiles('Documents');
```

### File Uploads

```php
// Upload from file
Storage::disk('onedrive')->putFile('avatars', new \Illuminate\Http\File('/path/to/avatar.jpg'));

// Upload with custom name
Storage::disk('onedrive')->putFileAs('avatars', $request->file('avatar'), 'profile.jpg');
```

### File Downloads

```php
// Download as response
return Storage::disk('onedrive')->download('document.pdf', 'my-document.pdf');
```

### File Sharing

```php
// Get sharing URL
$url = Storage::disk('onedrive')->getUrl('document.pdf');
```

## Troubleshooting

### 401 Unauthorized

- Verify your client ID and secret are correct
- Ensure the tenant ID is correct

### 403 Forbidden

- Ensure `Files.ReadWrite.All` permission is added
- Grant admin consent for the permissions
- Verify the user ID has access to OneDrive

### Token Expiration

Tokens are automatically cached and refreshed. If you see token errors:
- Check your cache configuration
- Ensure Laravel cache is working properly

### Caching Issues

This package uses Laravel's default cache. Make sure:
- Your `.env` file has a valid `CACHE_DRIVER`
- Redis or database cache is configured if using production
