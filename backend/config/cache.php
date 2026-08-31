<?php

use Illuminate\Support\Str;

return [

    'default' => env('CACHE_STORE', 'file'),

    'stores' => [

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

    ],

    'prefix' => Str::slug((string) env('APP_NAME', 'Dance with Death')).'-cache-',

];
