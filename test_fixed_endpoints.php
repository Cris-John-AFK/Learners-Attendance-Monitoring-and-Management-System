<?php

// Test script to check API endpoints
$baseUrl = 'http://localhost:8000/api';

// Test endpoints
$endpoints = [
    'health-check' => $baseUrl . '/health-check',
    'teacher-assignments' => $baseUrl . '/teachers/1/assignments',
    'seating-arrangement' => $baseUrl . '/student-management/sections/13/seating-arrangement?teacher_id=1',
    'attendance-statuses' => $baseUrl . '/attendance/statuses'
];

echo "Testing API endpoints...\n\n";

foreach ($endpoints as $name => $url) {
    echo "Testing {$name}: {$url}\n";
    
    // Initialize curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ CURL Error: {$error}\n";
    } else {
        echo "  HTTP Code: {$httpCode}\n";
        if ($httpCode == 200) {
            echo "  ✅ SUCCESS\n";
            $data = json_decode($response, true);
            if ($data) {
                echo "  Response keys: " . implode(', ', array_keys($data)) . "\n";
            }
        } else {
            echo "  ❌ FAILED\n";
            echo "  Response: " . substr($response, 0, 200) . "\n";
        }
    }
    echo "\n";
}