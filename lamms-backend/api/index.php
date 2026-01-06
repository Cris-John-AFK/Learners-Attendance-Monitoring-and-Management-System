<?php

// IMMEDIATE TEST: Verify this file is being reached
if (isset($_GET['ping'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'api/index.php is alive!', 'timestamp' => time()]);
    exit;
}

// DEBUG: Check BEFORE we modify anything
if (isset($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'debug_routing') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'ORIGINAL_REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'ORIGINAL_SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
        'PHP_SELF' => $_SERVER['PHP_SELF'],
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? '',
        'GET_PARAMS' => $_GET,
    ]);
    exit;
}

// Fix for Vercel routing where SCRIPT_NAME includes /api/index.php
// This causes Laravel to incorrectly strip the 'api' prefix from the request URI
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/api/index.php') !== false) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

// Forward Vercel requests to normal index.php
require __DIR__ . '/../public/index.php';
