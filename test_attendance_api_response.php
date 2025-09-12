<?php

$url = "http://127.0.0.1:8000/api/attendance-records/section/3?start_date=2025-09-01&end_date=2025-09-30";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";

$data = json_decode($response, true);
echo json_encode($data, JSON_PRETTY_PRINT);

if (isset($data['sessions'])) {
    echo "\n\n=== ANALYSIS ===\n";
    echo "Total sessions: " . count($data['sessions']) . "\n";
    
    foreach ($data['sessions'] as $session) {
        echo "\nSession ID: {$session['id']}\n";
        echo "Date: {$session['session_date']}\n";
        echo "Subject: {$session['subject']['name']}\n";
        echo "Records count: " . count($session['attendance_records']) . "\n";
        
        foreach ($session['attendance_records'] as $record) {
            echo "  Student ID: {$record['student_id']}, Status: {$record['attendance_status']['name']}\n";
        }
    }
}

if (isset($data['students'])) {
    echo "\n=== STUDENTS ===\n";
    foreach ($data['students'] as $student) {
        echo "Student ID: {$student['id']}, Name: {$student['firstName']} {$student['lastName']}\n";
    }
}
