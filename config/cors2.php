<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // 'allowed_origins' => ['*'],
    'allowed_origins' => ['http://localhost:3000', 'https://exeat.vercel.app','https://exeat.veritas.edu.ng',],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Set to true for cookie-based auth
];
