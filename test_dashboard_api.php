<?php

echo "=== TESTING DASHBOARD API ENDPOINTS ===\n";

// Test attendance summary endpoint
echo "\n1. Testing /api/attendance/summary?teacher_id=3\n";
$url = 'http://localhost:8000/api/attendance/summary?teacher_id=3';
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
            echo "   Sample students:\n";
            foreach (array_slice($data['data']['students'], 0, 3) as $student) {
                echo "   - {$student['name']} (Absences: {$student['total_absences']})\n";
            }
        }
    } else {
        echo "❌ FAILED: {$data['message']}\n";
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

// Test attendance trends endpoint
echo "\n2. Testing /api/attendance/trends?teacher_id=3\n";
$url = 'http://localhost:8000/api/attendance/trends?teacher_id=3';
$response = file_get_contents($url);
if ($response) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ SUCCESS: Chart data loaded\n";
        echo "   Labels: " . implode(', ', $data['data']['labels']) . "\n";
        echo "   Present data: " . implode(', ', $data['data']['datasets'][0]['data']) . "\n";
        echo "   Execution time: {$data['execution_time_ms']}ms\n";
    } else {
        echo "❌ FAILED: {$data['message']}\n";
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

// Test teacher dashboard endpoint
echo "\n3. Testing /api/teacher/3/dashboard\n";
$url = 'http://localhost:8000/api/teacher/3/dashboard';
$response = file_get_contents($url);
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['teacher'])) {
        echo "✅ SUCCESS: Teacher dashboard loaded\n";
        echo "   Teacher: {$data['teacher']['name']}\n";
        echo "   Subjects: " . count($data['subjects']) . "\n";
        echo "   Students with issues: " . count($data['studentsWithIssues']) . "\n";
        if (isset($data['performance']['load_time_ms'])) {
            echo "   Load time: {$data['performance']['load_time_ms']}ms\n";
        }
    } else {
        echo "❌ FAILED: Invalid response structure\n";
        echo "   Response: " . substr($response, 0, 200) . "...\n";
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

echo "\n=== TEST COMPLETE ===\n";

?>
