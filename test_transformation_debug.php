<?php

// Simulate the exact transformation that should happen in the frontend
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

echo "=== EXACT TRANSFORMATION SIMULATION ===\n\n";

// Generate date range like frontend does
$startDate = new DateTime('2025-09-01');
$endDate = new DateTime('2025-09-30');
$dateRange = [];

$current = clone $startDate;
while ($current <= $endDate) {
    $dateRange[] = $current->format('Y-m-d');
    $current->add(new DateInterval('P1D'));
}

echo "Date range count: " . count($dateRange) . "\n";
echo "Sample dates: " . implode(', ', array_slice($dateRange, 7, 5)) . "\n\n";

// Create attendance map exactly like the service
$attendanceMap = [];

if (isset($data['sessions'])) {
    foreach ($data['sessions'] as $session) {
        $sessionDate = $session['session_date'];
        
        foreach ($session['attendance_records'] as $record) {
            $studentKey = $record['student_id'] . '-' . $sessionDate;
            
            if (!isset($attendanceMap[$studentKey])) {
                $attendanceMap[$studentKey] = [];
            }
            
            $attendanceMap[$studentKey][] = [
                'status' => $record['attendance_status']['name'],
                'status_code' => $record['attendance_status']['code'],
                'arrival_time' => $record['arrival_time'],
                'remarks' => $record['remarks'],
                'subject' => $session['subject']['name'],
                'subject_id' => $session['subject']['id'],
                'marked_at' => $record['marked_at'] ?? null,
                'session_id' => $session['id']
            ];
        }
    }
}

// Transform to matrix like the service
$matrix = [];

if (isset($data['students'])) {
    foreach ($data['students'] as $student) {
        $row = [
            'id' => $student['id'],
            'name' => $student['name'],
            'gradeLevel' => $student['gradeLevel'],
            'section' => 'Unknown'
        ];
        
        // Add attendance data for each date
        foreach ($dateRange as $date) {
            $studentKey = $student['id'] . '-' . $date;
            $dayRecords = $attendanceMap[$studentKey] ?? [];
            
            if (empty($dayRecords)) {
                $row[$date] = null;
            } elseif (count($dayRecords) === 1) {
                // Single subject - show direct status
                $row[$date] = $dayRecords[0]['status'];
            } else {
                // Multiple subjects - calculate overall status
                $statuses = array_column($dayRecords, 'status');
                $presentCount = count(array_filter($statuses, fn($s) => $s === 'Present'));
                $absentCount = count(array_filter($statuses, fn($s) => $s === 'Absent'));
                $lateCount = count(array_filter($statuses, fn($s) => $s === 'Late'));
                $excusedCount = count(array_filter($statuses, fn($s) => $s === 'Excused'));
                
                // Determine overall status
                if ($absentCount === 0 && $lateCount === 0) {
                    $row[$date] = 'Present'; // All present/excused
                } elseif ($presentCount === 0 && $excusedCount === 0 && $lateCount === 0) {
                    $row[$date] = 'Absent'; // All absent
                } elseif ($lateCount > 0 && $absentCount === 0) {
                    $row[$date] = 'Late'; // Has late but no absent
                } else {
                    $row[$date] = 'Mixed'; // Mixed attendance
                }
            }
            
            // Store detailed records for expandable view
            $row[$date . '_details'] = $dayRecords;
        }
        
        $matrix[] = $row;
    }
}

echo "Matrix created with " . count($matrix) . " students\n\n";

foreach ($matrix as $student) {
    echo "Student {$student['id']} ({$student['name']}):\n";
    
    // Check specific dates that have data
    foreach (['2025-09-08', '2025-09-09', '2025-09-11'] as $date) {
        $status = $student[$date] ?? 'NULL';
        $details = $student[$date . '_details'] ?? [];
        echo "  $date: $status (details: " . count($details) . " records)\n";
    }
    echo "\n";
}

// Check what the frontend dateColumns would be for current month
echo "=== FRONTEND DATE COLUMNS SIMULATION ===\n";
$frontendStart = new DateTime('2025-09-01');
$frontendEnd = new DateTime('2025-09-30');
$frontendColumns = [];

$current = clone $frontendStart;
while ($current <= $frontendEnd) {
    $frontendColumns[] = $current->format('Y-m-d');
    $current->add(new DateInterval('P1D'));
}

echo "Frontend would generate " . count($frontendColumns) . " columns\n";
echo "Includes our data dates: " . (in_array('2025-09-08', $frontendColumns) ? 'YES' : 'NO') . "\n";
