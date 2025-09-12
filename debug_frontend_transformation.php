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
curl_close($ch);

$data = json_decode($response, true);

echo "=== DEBUGGING FRONTEND TRANSFORMATION ===\n\n";

// Check the date format in sessions
if (isset($data['sessions'])) {
    echo "Sessions found: " . count($data['sessions']) . "\n";
    foreach ($data['sessions'] as $session) {
        echo "Session Date: '{$session['session_date']}'\n";
        echo "Records count: " . count($session['attendance_records']) . "\n";
        
        foreach ($session['attendance_records'] as $record) {
            echo "  Student {$record['student_id']}: {$record['attendance_status']['name']}\n";
        }
        echo "\n";
    }
}

// Simulate the frontend date range generation
echo "=== SIMULATING FRONTEND DATE RANGE ===\n";
$startDate = new DateTime('2025-09-01');
$endDate = new DateTime('2025-09-30');
$dateRange = [];

$current = clone $startDate;
while ($current <= $endDate) {
    $dateRange[] = $current->format('Y-m-d');
    $current->add(new DateInterval('P1D'));
}

echo "Generated date range (first 10): " . implode(', ', array_slice($dateRange, 0, 10)) . "\n";
echo "Date range includes Sep 8? " . (in_array('2025-09-08', $dateRange) ? 'YES' : 'NO') . "\n";
echo "Date range includes Sep 9? " . (in_array('2025-09-09', $dateRange) ? 'YES' : 'NO') . "\n";
echo "Date range includes Sep 11? " . (in_array('2025-09-11', $dateRange) ? 'YES' : 'NO') . "\n";

// Simulate the transformation logic
echo "\n=== SIMULATING TRANSFORMATION ===\n";
$attendanceMap = [];

if (isset($data['sessions'])) {
    foreach ($data['sessions'] as $session) {
        $sessionDate = $session['session_date'];
        echo "Processing session date: $sessionDate\n";
        
        foreach ($session['attendance_records'] as $record) {
            $studentKey = $record['student_id'] . '-' . $sessionDate;
            
            if (!isset($attendanceMap[$studentKey])) {
                $attendanceMap[$studentKey] = [];
            }
            
            $attendanceMap[$studentKey][] = [
                'status' => $record['attendance_status']['name'],
                'subject' => $session['subject']['name']
            ];
        }
    }
}

echo "\nAttendance map keys: " . implode(', ', array_keys($attendanceMap)) . "\n";

// Check what happens for each student on each date
if (isset($data['students'])) {
    foreach ($data['students'] as $student) {
        echo "\nStudent {$student['id']} ({$student['name']}):\n";
        
        foreach (['2025-09-08', '2025-09-09', '2025-09-11'] as $date) {
            $studentKey = $student['id'] . '-' . $date;
            $dayRecords = $attendanceMap[$studentKey] ?? [];
            
            echo "  $date: ";
            if (empty($dayRecords)) {
                echo "NO DATA\n";
            } else {
                echo "Records: " . count($dayRecords) . " - ";
                foreach ($dayRecords as $record) {
                    echo "{$record['status']} ({$record['subject']}) ";
                }
                echo "\n";
            }
        }
    }
}
