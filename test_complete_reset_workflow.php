<?php

echo "🔄 === SEATING ARRANGEMENT RESET TEST === 🔄\n\n";

// Test the complete reset workflow
$sectionId = 13;
$teacherId = 1;

echo "📍 Testing section {$sectionId} with teacher {$teacherId}\n\n";

// Step 1: Check current state
echo "1️⃣  Checking current state...\n";
$getUrl = "http://localhost:8000/api/student-management/sections/{$sectionId}/seating-arrangement?teacher_id={$teacherId}";
$response = makeRequest('GET', $getUrl);
$currentData = $response['data'];
echo "Current last_updated: " . ($currentData['last_updated'] ?? 'null') . "\n";

// Step 2: Save a seating arrangement
echo "\n2️⃣  Saving test seating arrangement...\n";
$saveUrl = 'http://localhost:8000/api/student-management/seating-arrangement/save';
$saveData = [
    "section_id" => $sectionId,
    "subject_id" => null,
    "teacher_id" => $teacherId,
    "seating_layout" => [
        "rows" => 2,
        "columns" => 2,
        "seatPlan" => [
            [
                ["isOccupied" => true, "studentId" => 2, "status" => null],
                ["isOccupied" => true, "studentId" => 3, "status" => null]
            ],
            [
                ["isOccupied" => false, "studentId" => null, "status" => null],
                ["isOccupied" => false, "studentId" => null, "status" => null]
            ]
        ]
    ]
];

$response = makeRequest('POST', $saveUrl, $saveData);
echo $response['status'] == 200 ? "✅ Seating saved successfully\n" : "❌ Failed to save seating\n";

// Step 3: Verify it was saved
echo "\n3️⃣  Verifying seating was saved to database...\n";
$response = makeRequest('GET', $getUrl);
$savedData = $response['data'];
if ($savedData['last_updated'] !== null) {
    echo "✅ Seating arrangement found in database (last_updated: {$savedData['last_updated']})\n";
} else {
    echo "❌ Seating arrangement not found in database\n";
}

// Step 4: Reset the seating arrangement
echo "\n4️⃣  Resetting seating arrangement...\n";
$resetUrl = 'http://localhost:8000/api/student-management/seating-arrangement/reset';
$resetData = [
    "section_id" => $sectionId,
    "subject_id" => null,
    "teacher_id" => $teacherId
];

$response = makeRequest('POST', $resetUrl, $resetData);
if ($response['status'] == 200) {
    echo "✅ Reset successful!\n";
    echo "Deleted records: " . $response['data']['deleted_records'] . "\n";
} else {
    echo "❌ Reset failed!\n";
}

// Step 5: Verify the reset worked
echo "\n5️⃣  Verifying reset worked...\n";
$response = makeRequest('GET', $getUrl);
$resetData = $response['data'];

if ($resetData['last_updated'] === null) {
    echo "✅ SUCCESS! Seating arrangement cleared from database\n";
    echo "📋 Now showing default layout with students from section\n";
} else {
    echo "❌ FAILED! Data still exists in database\n";
    echo "Last updated: {$resetData['last_updated']}\n";
}

echo "\n🎯 === FINAL RESULT === 🎯\n";
if ($resetData['last_updated'] === null) {
    echo "🎉 SEATING ARRANGEMENT RESET WORKING PERFECTLY!\n";
    echo "✨ When you click 'Reset' in the frontend, it will now properly clear the database.\n";
} else {
    echo "⚠️  Reset functionality needs further investigation.\n";
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