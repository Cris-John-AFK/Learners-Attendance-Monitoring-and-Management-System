<?php

return [
    'paths' => ['api/*', 'teachers/*', 'teachers/*/assignments', 'sections', 'grades', 'subjects'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
