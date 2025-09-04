<?php

// Simple test script to check the API endpoints
echo "Testing API Endpoints\n";
echo "====================\n\n";

$endpoints = [
    'http://localhost:8000/api/health-check',
    'http://localhost:8000/api/student-management/sections/13/students?teacher_id=1',
    'http://localhost:8000/api/student-management/sections/13/seating-arrangement?teacher_id=1',
];

foreach ($endpoints as $endpoint) {
    echo "Testing: $endpoint\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($endpoint, false, $context);
    
    if ($response === false) {
        echo "❌ Request failed\n";
        $error = error_get_last();
        if ($error) {
            echo "Error: " . $error['message'] . "\n";
        }
    } else {
        echo "✅ Request successful - " . strlen($response) . " bytes\n";
        $data = json_decode($response, true);
        if ($data) {
            if (isset($data['status'])) {
                echo "Status: " . $data['status'] . "\n";
            }
            if (isset($data['students'])) {
                echo "Students found: " . count($data['students']) . "\n";
            }
            if (isset($data['error'])) {
                echo "API Error: " . $data['error'] . "\n";
            }
        }
    }
    echo "\n";
}

echo "Test completed!\n";
?>