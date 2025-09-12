<?php

$url = "http://127.0.0.1:8000/api/sections";

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

echo "=== SECTIONS API RESPONSE ===\n";
echo "HTTP Status: $httpCode\n";

$data = json_decode($response, true);
if (isset($data['sections'])) {
    foreach ($data['sections'] as $section) {
        echo "Section ID: {$section['id']}, Name: {$section['name']}, Grade: {$section['gradeLevel']}\n";
        
        if (isset($section['students'])) {
            echo "  Students in section:\n";
            foreach ($section['students'] as $student) {
                echo "    Student ID: {$student['id']}, Name: {$student['firstName']} {$student['lastName']}\n";
            }
        }
    }
}

// Test students endpoint directly
echo "\n=== STUDENTS API RESPONSE ===\n";
$url = "http://127.0.0.1:8000/api/students";

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
$data = json_decode($response, true);
if (isset($data['students'])) {
    foreach ($data['students'] as $student) {
        echo "Student ID: {$student['id']}, Name: {$student['firstName']} {$student['lastName']}, Section: {$student['section_id']}\n";
    }
}
