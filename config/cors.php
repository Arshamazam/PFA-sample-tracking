<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    | Locked to explicit app domains. The Flutter app uses bearer tokens (not
    | cookies), so credentials stay off. Set CORS_ALLOWED_ORIGINS to a
    | comma-separated list of the panel/public domains in production.
    */
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', ''))),
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
