<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'https://exeat.vercel.app',
        'https://attendance.veritas.edu.ng',
    ],

    'allowed_origins_patterns' => [
        '^https:\/\/.*\.veritas\.edu\.ng$',
        '^https:\/\/.*\.vercel\.app$',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
