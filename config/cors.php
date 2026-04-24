<?php

return [
    /*
    | The 'paths' must match the URL pattern of your API.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    | Allow all methods (GET, POST, OPTIONS, etc.)
    */
    'allowed_methods' => ['*'],

    /*
    | Remove the '*' from here! Only put specific, static URLs.
    */
    'allowed_origins' => [
        'https://matemcollege.com',
        // Add your production Flutter Web URL here if it's different
    ],

    /*
    | 🔥 FIX: Use a Regex pattern to allow ANY localhost port for Flutter Web
    */
    'allowed_origins_patterns' => [
        '#^http://localhost:\d+$#'
    ],

    /*
    | Allow all headers to prevent random blocks
    */
    'allowed_headers' => ['*'],

    /*
    | Headers the client is allowed to read
    */
    'exposed_headers' => [
        'Authorization',
    ],

    'max_age' => 600,

    /*
    | Since this is true, we cannot use '*' in allowed_origins
    */
    'supports_credentials' => true,
];
