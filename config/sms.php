<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    | Which SMS gateway to use. Local/testing default to "log" so nothing is ever
    | sent to a real network without an explicit SMS_DRIVER. Swap to PFA's provider
    | by adding a driver class and pointing SMS_DRIVER at it.
    */
    'driver' => env('SMS_DRIVER', 'log'),

    'log_channel' => env('SMS_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Driver registry
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'log' => ['class' => \App\Sms\LogGateway::class],
        'null' => ['class' => \App\Sms\NullGateway::class],
        'sendpk' => [
            'class' => \App\Sms\SendPkGateway::class,
            'endpoint' => env('SENDPK_ENDPOINT'),
            'api_key' => env('SENDPK_API_KEY'),
            'sender' => env('SENDPK_SENDER', 'PFA'),
        ],
    ],

    // Base for shortened tracking links used in messages (see /t/{event_code}).
    'short_link_base' => env('SMS_SHORT_LINK_BASE', env('APP_URL').'/t'),
];
