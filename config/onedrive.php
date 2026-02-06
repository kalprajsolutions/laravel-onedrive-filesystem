<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph Client ID
    |--------------------------------------------------------------------------
    |
    | The application client ID from Azure AD app registration.
    |
    */
    'client_id' => env('GRAPH_CLIENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph Tenant ID
    |--------------------------------------------------------------------------
    |
    | The tenant ID from Azure AD. Use 'common' for multi-tenant apps
    | or your specific tenant ID.
    |
    */
    'tenant_id' => env('GRAPH_TENANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph Client Secret
    |--------------------------------------------------------------------------
    |
    | The client secret from Azure AD app registration.
    |
    */
    'client_secret' => env('GRAPH_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | OneDrive User ID
    |--------------------------------------------------------------------------
    |
    | The user ID (email or object ID) of the OneDrive for Business account.
    |
    */
    'user_id' => env('GRAPH_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    |
    | Optional base path within the OneDrive root folder.
    | All operations will be relative to this path.
    |
    */
    'base_path' => env('GRAPH_BASE_PATH'),
];
