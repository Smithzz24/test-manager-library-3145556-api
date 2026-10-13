<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'library,public',
            'timezone' => 'UTC',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'mask_bindings_in_exception_messages' => true,
        ],
    ],
    'migrations' => [
        'table' => 'public.migrations',
        'update_date_on_publish' => false,
    ],
];
