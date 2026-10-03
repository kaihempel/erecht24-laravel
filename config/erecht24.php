<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Required credentials for talking to the eRecht24 API. None of these
    | ship with a default value; attempting to use them while empty throws
    | a MissingConfigurationException only at the point of use, not at
    | service-provider boot.
    |
    */

    'api_key' => env('ERECHT24_API_KEY'),

    'plugin_key' => env('ERECHT24_PLUGIN_KEY'),

    'push_secret' => env('ERECHT24_PUSH_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Push Webhook Path
    |--------------------------------------------------------------------------
    */

    'push_path' => env('ERECHT24_PUSH_PATH', '/api/erecht24/push'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('ERECHT24_BASE_URL', 'https://api.e-recht24.de/v2'),

    /*
    |--------------------------------------------------------------------------
    | Text Languages
    |--------------------------------------------------------------------------
    |
    | Comma separated list of language codes to sync. Only `de` and `en`
    | are currently supported.
    |
    */

    'text_languages' => env('ERECHT24_TEXT_LANGUAGES', 'de,en'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */

    'disk' => env('ERECHT24_DISK', 'local'),

    'directory' => env('ERECHT24_DIRECTORY', 'erecht24'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => env('ERECHT24_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue connection/name used for dispatching eRecht24 jobs. No
    | dedicated .env mapping is provided; null falls back to the
    | application's default connection/queue.
    |
    */

    'queue' => [
        'connection' => null,
        'name' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Retry/Backoff
    |--------------------------------------------------------------------------
    |
    | Tuning for the queued legal text sync job: number of attempts and the
    | exponential backoff (in seconds) between them. The backoff array shape
    | is not env-mapped since a single env var cannot represent it cleanly.
    |
    */

    'sync' => [
        'tries' => env('ERECHT24_SYNC_TRIES', 3),
        'backoff' => [60, 300, 900],
    ],

];
