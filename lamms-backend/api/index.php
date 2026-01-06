<?php

// Fix for Vercel routing where SCRIPT_NAME includes /api/index.php
// This causes Laravel to incorrectly strip the 'api' prefix from the request URI
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/api/index.php') !== false) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

// DEBUG: Temporary check to see what Vercel sends
if (isset($_GET['debug_routing'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
        'PHP_SELF' => $_SERVER['PHP_SELF'],
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? '',
    ]);
    exit;
}

// Forward Vercel requests to normal index.php
require __DIR__ . '/../public/index.php';
