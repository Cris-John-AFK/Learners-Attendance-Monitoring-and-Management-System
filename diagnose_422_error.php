<?php

echo "=== TESTING ATTENDANCE MARKING - 422 ERROR DIAGNOSIS ===\n\n";

// Test the attendance marking endpoint that's causing 422 errors
$url = 'http://localhost:8000/api/attendance';
$testData = [
    "section_id" => 13,
    "subject_id" => 1,
    "teacher_id" => 1,
    "date" => "2025-09-04",
    "attendance" => [
        [
            "student_id" => 2,
            "attendance_status_id" => 1,
            "remarks" => "Present"
        ],
        [
            "student_id" => 3,
            "attendance_status_id" => 2,
            "remarks" => "Absent"
        ]
    ]
];

echo "Testing URL: {$url}\n";
echo "Data being sent:\n" . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: {$httpCode}\n";
echo "Response Body:\n{$response}\n\n";

if ($httpCode == 422) {
    echo "🔍 ANALYZING 422 ERROR:\n";
    $errorData = json_decode($response, true);
    
    if (isset($errorData['errors'])) {
        echo "Validation Errors Found:\n";
        foreach ($errorData['errors'] as $field => $messages) {
            echo "- {$field}: " . implode(', ', $messages) . "\n";
        }
    }
    
    if (isset($errorData['message'])) {
        echo "Error Message: " . $errorData['message'] . "\n";
    }
} elseif ($httpCode == 200) {
    echo "✅ Attendance marking successful!\n";
} else {
    echo "❌ Unexpected error code: {$httpCode}\n";
}

// Also check what students exist in section 13
echo "\n=== CHECKING STUDENTS IN SECTION 13 ===\n";
require_once 'lamms-backend/vendor/autoload.php';
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Section;

$section = Section::find(13);
if ($section) {
    $students = $section->activeStudents()->get();
    echo "Students in section 13:\n";
    foreach ($students as $student) {
        echo "- ID: {$student->id}, Name: {$student->name}, StudentId: {$student->studentId}\n";
    }
} else {
    echo "Section 13 not found!\n";
}