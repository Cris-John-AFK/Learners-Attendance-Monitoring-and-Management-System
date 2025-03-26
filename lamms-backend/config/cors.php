<?php

return [
    'paths' => ['api/*'],
    'allowed_origins' => [env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
