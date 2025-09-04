<?php

echo "=== Testing Attendance API ===\n\n";

// Test marking attendance
$url = 'http://localhost:8000/api/attendance';
$data = [
    "section_id" => 13,
    "subject_id" => 1,
    "teacher_id" => 1,
    "date" => "2025-09-04",
    "attendance" => [
        [
            "student_id" => 3,
            "attendance_status_id" => 1,
            "remarks" => "Present in class"
        ],
        [
            "student_id" => 4,
            "attendance_status_id" => 2,
            "remarks" => "Absent"
        ]
    ]
];

echo "Testing attendance marking...\n";
echo "URL: {$url}\n";
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($httpCode == 200) {
    echo "✅ SUCCESS - Attendance marked\n";
    $data = json_decode($response, true);
    if ($data) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED\n";
    echo "Response: {$response}\n";
}