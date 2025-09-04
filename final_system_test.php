<?php

echo "🔥 === LAMMS SYSTEM FINAL TEST === 🔥\n\n";

$baseUrl = 'http://localhost:8000/api';
$testResults = [];

// Test 1: Health Check
echo "1️⃣  Testing API Health Check...\n";
$response = makeRequest('GET', $baseUrl . '/health-check');
$testResults['health_check'] = $response['status'] == 200;
echo $response['status'] == 200 ? "✅ PASSED\n" : "❌ FAILED\n";

// Test 2: Teacher Assignments 
echo "\n2️⃣  Testing Teacher Assignments...\n";
$response = makeRequest('GET', $baseUrl . '/teachers/1/assignments');
$testResults['teacher_assignments'] = $response['status'] == 200;
echo $response['status'] == 200 ? "✅ PASSED\n" : "❌ FAILED\n";

// Test 3: Seating Arrangements
echo "\n3️⃣  Testing Seating Arrangements...\n";
$response = makeRequest('GET', $baseUrl . '/student-management/sections/13/seating-arrangement?teacher_id=1');
$testResults['seating_arrangements'] = $response['status'] == 200;
echo $response['status'] == 200 ? "✅ PASSED\n" : "❌ FAILED\n";

// Test 4: Attendance Statuses
echo "\n4️⃣  Testing Attendance Statuses...\n";
$response = makeRequest('GET', $baseUrl . '/attendance/statuses');
$testResults['attendance_statuses'] = $response['status'] == 200;
echo $response['status'] == 200 ? "✅ PASSED\n" : "❌ FAILED\n";

// Test 5: Attendance Marking
echo "\n5️⃣  Testing Attendance Marking...\n";
$attendanceData = [
    "section_id" => 13,
    "subject_id" => 1,
    "teacher_id" => 1,
    "date" => "2025-09-04",
    "attendance" => [
        [
            "student_id" => 3,
            "attendance_status_id" => 1,
            "remarks" => "Present - Final Test"
        ]
    ]
];
$response = makeRequest('POST', $baseUrl . '/attendance', $attendanceData);
$testResults['attendance_marking'] = $response['status'] == 200;
echo $response['status'] == 200 ? "✅ PASSED\n" : "❌ FAILED\n";

// Summary
echo "\n🎯 === TEST SUMMARY === 🎯\n";
$passed = array_sum($testResults);
$total = count($testResults);

foreach ($testResults as $test => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo "- " . ucwords(str_replace('_', ' ', $test)) . ": {$status}\n";
}

echo "\n📊 Results: {$passed}/{$total} tests passed\n";

if ($passed == $total) {
    echo "🎉 ALL SYSTEMS OPERATIONAL! 🎉\n";
    echo "✨ Your LAMMS attendance and seating system is working perfectly!\n";
} else {
    echo "⚠️  Some issues remain. Check the failed tests above.\n";
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