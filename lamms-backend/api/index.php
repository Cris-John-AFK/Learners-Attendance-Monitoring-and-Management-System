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

// Fix for Vercel: Strip /api prefix from REQUEST_URI
// Vercel passes the full path like /api/auth/login
// But Laravel's RouteServiceProvider also adds /api prefix
// So we need to strip it here to avoid /api/api/auth/login
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
    // Preserve query string if it exists
    $uri = $_SERVER['REQUEST_URI'];
    $queryPos = strpos($uri, '?');
    if ($queryPos !== false) {
        $path = substr($uri, 0, $queryPos);
        $query = substr($uri, $queryPos);
        $_SERVER['REQUEST_URI'] = substr($path, 4) . $query; // Remove '/api' but keep query
    } else {
        $_SERVER['REQUEST_URI'] = substr($uri, 4); // Remove '/api'
    }
}

// Fix for Vercel routing where SCRIPT_NAME includes /api/index.php
// This causes Laravel to incorrectly strip the 'api' prefix from the request URI
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/api/index.php') !== false) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

// Forward Vercel requests to normal index.php
require __DIR__ . '/../public/index.php';
