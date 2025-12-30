<?php

return [
    /*
    | The 'paths' must match the URL pattern of your API.
    */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
    | FOR TESTING
    | GitHub Pages origin.
    */
    'allowed_origins' => [
        'https://nathannkweto.github.io',
        'http://localhost:5000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
