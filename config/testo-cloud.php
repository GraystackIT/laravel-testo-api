<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Testo API Credentials
    |--------------------------------------------------------------------------
    | Your Testo Saveris Data API client ID (username) and client secret (password).
    | Obtain these from your Testo account.
    */
    'client_id'     => env('TESTO_CLIENT_ID'),
    'client_secret' => env('TESTO_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Region
    |--------------------------------------------------------------------------
    | The API region: 'eu' (Europe), 'am' (Americas), 'ap' (Asia-Pacific).
    */
    'region' => env('TESTO_REGION', 'eu'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | 'p' for production (live data), 'i' for integration/testing.
    */
    'environment' => env('TESTO_ENVIRONMENT', 'p'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    | Timeout in seconds for standard API requests.
    | Downloads use a separate, longer timeout.
    */
    'http_timeout'          => env('TESTO_HTTP_TIMEOUT', 30),
    'download_timeout'      => env('TESTO_DOWNLOAD_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Token Cache Buffer
    |--------------------------------------------------------------------------
    | Seconds to subtract from the token's expires_in to avoid using an
    | almost-expired token. Defaults to 60 seconds.
    */
    'token_cache_ttl_buffer_seconds' => env('TESTO_TOKEN_CACHE_TTL_BUFFER', 60),
];
