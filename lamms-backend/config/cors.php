<?php

return [
<<<<<<< HEAD
    'paths' => ['api/*'],
    'allowed_origins' => [env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')],
    'allowed_methods' => ['*'],
=======
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173'], // Vue's default dev server port
    'allowed_origins_patterns' => [],
>>>>>>> 728707841b8ef60dff667d6cd6cfaef6a147bfe8
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
