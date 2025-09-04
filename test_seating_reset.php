<?php

echo "=== Testing Seating Arrangement Reset ===\n\n";

// First, let's create a seating arrangement to test with
$saveUrl = 'http://localhost:8000/api/student-management/seating-arrangement/save';
$saveData = [
    "section_id" => 13,
    "subject_id" => null,
    "teacher_id" => 1,
    "seating_layout" => [
        "rows" => 3,
        "columns" => 3,
        "seatPlan" => [
            [
                ["isOccupied" => true, "studentId" => 3, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null]
            ],
            [
                ["isOccupied" => false, "studentId" => null, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null]
            ],
            [
                ["isOccupied" => false, "studentId" => null, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null]
            ]
        ]
    ]
];

echo "1. Creating test seating arrangement...\n";
$response = makeRequest('POST', $saveUrl, $saveData);
echo $response['status'] == 200 ? "✅ Test data created\n" : "❌ Failed to create test data\n";

// Now test the reset endpoint
$resetUrl = 'http://localhost:8000/api/student-management/seating-arrangement/reset';
$resetData = [
    "section_id" => 13,
    "subject_id" => null,
    "teacher_id" => 1
];

echo "\n2. Testing reset endpoint...\n";
$response = makeRequest('POST', $resetUrl, $resetData);
echo "HTTP Status: " . $response['status'] . "\n";

if ($response['status'] == 200) {
    echo "✅ Reset successful!\n";
    echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Reset failed!\n";
    echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
}

// Verify the data is gone
echo "\n3. Verifying data is cleared...\n";
$getUrl = 'http://localhost:8000/api/student-management/sections/13/seating-arrangement?teacher_id=1';
$response = makeRequest('GET', $getUrl);

if ($response['status'] == 200) {
    $data = $response['data'];
    if (isset($data['last_updated']) && $data['last_updated'] === null) {
        echo "✅ Seating arrangement successfully cleared from database!\n";
    } else {
        echo "⚠️  Data might still exist in database\n";
    }
} else {
    echo "❌ Failed to verify reset\n";
}

function makeRequest($method, $url, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    if ($method == 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}