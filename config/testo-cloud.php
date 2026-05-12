<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Testo API Key
    |--------------------------------------------------------------------------
    | Your Smart Connect API key. Generate this from the Smart Connect home page
    | (valid for up to one year). Pass it via the x-custom-api-key header.
    */
    'api_key' => env('TESTO_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Region
    |--------------------------------------------------------------------------
    | The API region: 'eu' (Europe), 'am' (Americas), 'ap' (Asia-Pacific).
    */
    'region' => env('TESTO_REGION', 'eu'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    | Timeout in seconds for standard API requests.
    | Downloads use a separate, longer timeout.
    */
    'http_timeout'     => env('TESTO_HTTP_TIMEOUT', 30),
    'download_timeout' => env('TESTO_DOWNLOAD_TIMEOUT', 120),
];
