<?php

$semaphoreNamespace = substr(hash('sha256', implode('|', [
    env('DB_HOST', '127.0.0.1'),
    env('DB_PORT', '3306'),
    env('DB_USERNAME', 'forge'),
])), 0, 16);

$semaphoreDirectory = storage_path('framework/db-capacity-'.$semaphoreNamespace);

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP database capacity guard
    |--------------------------------------------------------------------------
    |
    | Keep concurrent Laravel requests below MySQL's per-user connection limit.
    | The remaining connections are intentionally reserved for queue workers,
    | scheduled commands and operational access.
    |
    */

    'guard' => [
        'enabled' => env('DB_CAPACITY_GUARD_ENABLED', env('APP_ENV') === 'production'),
        'slots' => env('DB_CAPACITY_GUARD_SLOTS', 40),
        'wait_milliseconds' => env('DB_CAPACITY_GUARD_WAIT_MS', 150),
        'retry_after_seconds' => env('DB_CAPACITY_GUARD_RETRY_AFTER', 1),
        // Production storage should be durable/shared by concurrent releases.
        // Apps sharing one DB user can coordinate by using the exact same path.
        'directory' => env('DB_CAPACITY_GUARD_DIRECTORY', $semaphoreDirectory),
    ],

    'ip_hash_key' => env('DB_CAPACITY_IP_HASH_KEY', env('APP_KEY', '')),
];
