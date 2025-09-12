<?php

// Debug the exact calculation differences between main table and detail dialog
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

echo "=== DEBUGGING ABSENCE CALCULATION MISMATCH ===\n\n";

// Generate date range like frontend does
$startDate = new DateTime('2025-09-01');
$endDate = new DateTime('2025-09-30');
$dateRange = [];

$current = clone $startDate;
while ($current <= $endDate) {
    $dateRange[] = $current->format('Y-m-d');
    $current->add(new DateInterval('P1D'));
}

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

// Find G3 TEst (student ID 11)
$g3TestStudent = null;
foreach ($matrix as $student) {
    if ($student['id'] == 11) {
        $g3TestStudent = $student;
        break;
    }
}

if ($g3TestStudent) {
    echo "=== G3 TEst Student (ID: 11) Analysis ===\n\n";
    
    // Method 1: Main table calculation (calculateAbsences function)
    $mainTableAbsences = 0;
    foreach ($dateRange as $date) {
        $status = $g3TestStudent[$date];
        if ($status === 'Absent') {
            $mainTableAbsences++;
        } elseif ($status === 'Mixed') {
            // Check detailed records for mixed days
            $details = $g3TestStudent[$date . '_details'] ?? [];
            $hasAbsent = false;
            foreach ($details as $record) {
                if ($record['status'] === 'Absent') {
                    $hasAbsent = true;
                    break;
                }
            }
            if ($hasAbsent) {
                $mainTableAbsences++;
            }
        }
    }
    
    // Method 2: Detail dialog calculation (calculateStudentStats function)
    $detailDialogAbsences = 0;
    $detailDialogLate = 0;
    $total = 0;
    
    foreach ($dateRange as $date) {
        $status = $g3TestStudent[$date];
        if ($status) {
            $total++;
            switch ($status) {
                case 'Absent':
                    $detailDialogAbsences++;
                    break;
                case 'Late':
                    $detailDialogLate++;
                    break;
                case 'Mixed':
                    // For mixed days, count detailed records
                    $details = $g3TestStudent[$date . '_details'] ?? [];
                    foreach ($details as $record) {
                        if ($record['status'] === 'Absent') $detailDialogAbsences++;
                        elseif ($record['status'] === 'Late') $detailDialogLate++;
                    }
                    break;
            }
        }
    }
    
    echo "Main Table Calculation (calculateAbsences): $mainTableAbsences absences\n";
    echo "Detail Dialog Calculation (calculateStudentStats): $detailDialogAbsences absences\n\n";
    
    echo "=== Day-by-day breakdown ===\n";
    foreach ($dateRange as $date) {
        $status = $g3TestStudent[$date];
        $details = $g3TestStudent[$date . '_details'] ?? [];
        
        if ($status && count($details) > 0) {
            echo "$date: Status = $status\n";
            foreach ($details as $i => $record) {
                echo "  Subject {$record['subject']}: {$record['status']}\n";
            }
            
            if ($status === 'Mixed') {
                $absentInDetails = count(array_filter($details, fn($r) => $r['status'] === 'Absent'));
                echo "  -> Main table would count: " . ($absentInDetails > 0 ? "1 absence" : "0 absences") . "\n";
                echo "  -> Detail dialog would count: $absentInDetails absences\n";
            }
            echo "\n";
        }
    }
}
