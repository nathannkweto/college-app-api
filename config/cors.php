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
    | FOR TESTING
    | GitHub Pages origin.
    */
    'allowed_origins' => [
        'https://matemcollege.com',
        'https://*.matemcollege.com',
    ],

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
