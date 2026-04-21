<?php

return [
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
