<?php

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'local_domain' => parse_url((string) env('APP_URL', 'http://localhost:8000'), PHP_URL_HOST),
        ],

        'log' => [
            'transport' => 'log',
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@dancewithdeath.test'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Dance with Death')),
    ],

];
