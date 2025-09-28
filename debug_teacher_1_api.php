<?php

echo "=== TESTING TEACHER 1 API ENDPOINTS ===\n";

// Test attendance summary endpoint for Teacher 1 (Maria Santos)
echo "\n1. Testing /api/attendance/summary?teacher_id=1&period=week&view_type=subject&subject_id=2\n";
$url = 'http://localhost:8000/api/attendance/summary?teacher_id=1&period=week&view_type=subject&subject_id=2';
$response = file_get_contents($url);
if ($response) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ SUCCESS: Found {$data['data']['total_students']} students\n";
        echo "   Average attendance: {$data['data']['average_attendance']}%\n";
        echo "   Students with warning: {$data['data']['students_with_warning']}\n";
        echo "   Students with critical: {$data['data']['students_with_critical']}\n";
        echo "   Execution time: {$data['execution_time_ms']}ms\n";
        
        if (!empty($data['data']['students'])) {
            echo "   Students found:\n";
            foreach ($data['data']['students'] as $student) {
                echo "   - ID: {$student['student_id']}, Name: {$student['name']}, Absences: {$student['total_absences']}, Present: {$student['total_present']}\n";
            }
        } else {
            echo "   ❌ NO STUDENTS IN RESPONSE!\n";
        }
        
        echo "\n   Full response structure:\n";
        echo "   " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ FAILED: {$data['message']}\n";
        if (isset($data['error'])) {
            echo "   Error: {$data['error']}\n";
        }
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

// Test students endpoint directly
echo "\n2. Testing TeacherAttendanceService endpoint\n";
$url = 'http://localhost:8000/api/teachers/1/sections/1/subjects/2/students';
$response = file_get_contents($url);
if ($response) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ SUCCESS: Found {$data['count']} students\n";
        echo "   Students:\n";
        foreach ($data['students'] as $student) {
            echo "   - ID: {$student['id']}, Name: {$student['name']}\n";
        }
    } else {
        echo "❌ FAILED: {$data['message']}\n";
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

echo "\n=== TEST COMPLETE ===\n";

?>
