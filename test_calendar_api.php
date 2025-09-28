<?php

echo "=== TESTING CALENDAR ATTENDANCE API ===\n";

// Test the new attendance records endpoint for calendar
echo "\n1. Testing /api/attendance/records for student calendar\n";
$url = 'http://localhost:8000/api/attendance/records?student_ids=14&subject_id=2&month=8&year=2025'; // September (month 8 = September in JS)
$response = file_get_contents($url);
if ($response) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ SUCCESS: Found {$data['count']} attendance records\n";
        echo "   Execution time: {$data['execution_time_ms']}ms\n";
        
        if (!empty($data['records'])) {
            echo "   Records for calendar:\n";
            foreach (array_slice($data['records'], 0, 10) as $record) {
                echo "   - Date: {$record['date']}, Status: {$record['status']}\n";
            }
        } else {
            echo "   ❌ NO RECORDS FOUND!\n";
        }
    } else {
        echo "❌ FAILED: {$data['message']}\n";
        if (isset($data['error'])) {
            echo "   Error: {$data['error']}\n";
        }
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

// Test with multiple students
echo "\n2. Testing with multiple students (14,16,20)\n";
$url = 'http://localhost:8000/api/attendance/records?student_ids=14,16,20&subject_id=2&month=8&year=2025';
$response = file_get_contents($url);
if ($response) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ SUCCESS: Found {$data['count']} total records for all students\n";
        echo "   Execution time: {$data['execution_time_ms']}ms\n";
        
        // Group by student
        $byStudent = [];
        foreach ($data['records'] as $record) {
            $byStudent[$record['studentId']][] = $record;
        }
        
        foreach ($byStudent as $studentId => $records) {
            echo "   Student {$studentId}: " . count($records) . " records\n";
        }
    } else {
        echo "❌ FAILED: {$data['message']}\n";
    }
} else {
    echo "❌ FAILED: Could not connect to API\n";
}

echo "\n=== TEST COMPLETE ===\n";

?>
