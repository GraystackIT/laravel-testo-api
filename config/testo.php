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

    /*
    |--------------------------------------------------------------------------
    | Automatic Measurement Storage
    |--------------------------------------------------------------------------
    | When enabled, the testo:fetch-measurements command will persist parsed
    | measurement rows into the testo_measurements database table.
    | Set to false to fetch and display data without writing to the database.
    */
    'store_measurements' => env('TESTO_STORE_MEASUREMENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Polling Settings
    |--------------------------------------------------------------------------
    | Controls how the fetch command waits for an async export to complete.
    | poll_interval_seconds — pause between each status check
    | poll_max_attempts     — give up after this many checks (total wait ≈ interval × max)
    */
    'poll_interval_seconds' => env('TESTO_POLL_INTERVAL', 5),
    'poll_max_attempts'     => env('TESTO_POLL_MAX_ATTEMPTS', 60),

    /*
    |--------------------------------------------------------------------------
    | Default Date Range
    |--------------------------------------------------------------------------
    | When --from is omitted from testo:fetch-measurements, the command fetches
    | data starting this many days before today.
    */
    'default_from_days' => env('TESTO_DEFAULT_FROM_DAYS', 7),
];
