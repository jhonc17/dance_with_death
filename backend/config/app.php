<?php

return [

    'name' => env('APP_NAME', 'Dance with Death'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost:8000'),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'locale' => 'en',

    'fallback_locale' => 'en',

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

];
