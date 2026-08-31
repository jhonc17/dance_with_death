<?php

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'dance_with_death'),
            'username' => env('DB_USERNAME', 'dance'),
            'password' => env('DB_PASSWORD', 'dance_secret'),
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
    ],

];
