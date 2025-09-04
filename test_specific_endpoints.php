<?php

$endpoints = [
    'teacher-assignments' => 'http://localhost:8000/api/teachers/1/assignments',
    'seating-arrangement' => 'http://localhost:8000/api/student-management/sections/13/seating-arrangement?teacher_id=1'
];

foreach ($endpoints as $name => $url) {
    echo "\n=== Testing {$name} ===\n";
    echo "URL: {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: {$httpCode}\n";
    if ($httpCode == 200) {
        echo "✅ SUCCESS\n";
        $data = json_decode($response, true);
        if ($data) {
            echo "Response preview: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
    } else {
        echo "❌ FAILED\n";
        echo "Response: {$response}\n";
    }
}