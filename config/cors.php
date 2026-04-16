<?php

return [

    /*
    | The 'paths' must match the URL pattern of your API.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
    | Allowed origins (comma-separated in .env / GitHub secret)
    */
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [
        'Authorization',
    ],

    'max_age' => 600,

    'supports_credentials' => true,
];
