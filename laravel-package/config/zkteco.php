<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default ZKTeco Device Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default device connection that will be used
    | when connecting to ZKTeco access panel devices. You can configure
    | multiple devices and switch between them as needed.
    |
    */
    'default' => env('ZKTECO_DEFAULT_DEVICE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Device Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each ZKTeco device
    | that is used by your application. A default configuration has been added
    | for development purposes.
    |
    */
    'devices' => [
        'default' => [
            'ip' => env('ZKTECO_IP', '192.168.1.201'),
            'port' => env('ZKTECO_PORT', 4370),
            'password' => env('ZKTECO_PASSWORD', 0),
            'timeout' => env('ZKTECO_TIMEOUT', 5000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezone ID
    |--------------------------------------------------------------------------
    |
    | The default timezone ID used for user authorization. ZKTeco devices
    | use timezone IDs to control when users can access doors.
    |
    */
    'default_timezone_id' => env('ZKTECO_DEFAULT_TIMEZONE_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Buffer Sizes
    |--------------------------------------------------------------------------
    |
    | Configure the buffer sizes for reading data from devices.
    |
    */
    'buffer_sizes' => [
        'huge' => 20 * 1024 * 1024,   // 20MB for large data sets
        'large' => 2 * 1024 * 1024,    // 2MB for normal operations
    ],
];
