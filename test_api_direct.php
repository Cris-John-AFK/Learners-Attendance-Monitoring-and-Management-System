<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Testing Attendance Records API Query Directly ===\n";

// Test the exact query from AttendanceRecordsController
$sectionId = 1;
$startDate = '2025-09-17';
$endDate = '2025-09-17';
$subjectId = 3;

echo "Testing query for Section: $sectionId, Date: $startDate, Subject: $subjectId\n\n";

// Get attendance sessions with records (same query as controller)
$query = DB::table('attendance_sessions as s')
    ->join('attendance_records as r', 's.id', '=', 'r.attendance_session_id')
    ->join('attendance_statuses as st', 'r.attendance_status_id', '=', 'st.id')
    ->leftJoin('subjects as sub', 's.subject_id', '=', 'sub.id')
    ->where('s.section_id', $sectionId)
    ->whereBetween('s.session_date', [$startDate, $endDate])
    ->where('r.is_current_version', true);

if ($subjectId) {
    $query->where('s.subject_id', $subjectId);
}

$records = $query->select([
    's.id as session_id',
    's.session_date',
    's.subject_id',
    'sub.name as subject_name',
    'r.student_id',
    'r.arrival_time',
    'r.marked_at',
    'r.remarks',
    'st.name as status_name',
    'st.code as status_code'
])->get();

echo "Records found: " . $records->count() . "\n";
foreach ($records as $record) {
    echo "Student ID: {$record->student_id}, Status: {$record->status_name}, Session: {$record->session_id}\n";
}

// Get students for the section (same query as controller)
echo "\n=== Students Query ===\n";
$students = DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->where('ss.section_id', $sectionId)
    ->where('sd.isActive', true)
    ->where('ss.is_active', true)
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.gradeLevel')
    ->get();

echo "Students found: " . $students->count() . "\n";
foreach ($students as $student) {
    echo "Student: {$student->firstName} {$student->lastName} (ID: {$student->id})\n";
}

// Test the transformation logic
echo "\n=== Transformation Logic ===\n";
$sessions = [];
$sessionGroups = $records->groupBy('session_id');

foreach ($sessionGroups as $sessionId => $sessionRecords) {
    $session = [
        'id' => $sessionId,
        'session_date' => $sessionRecords->first()->session_date,
        'subject' => [
            'id' => $sessionRecords->first()->subject_id,
            'name' => $sessionRecords->first()->subject_name
        ],
        'attendance_records' => []
    ];

    foreach ($sessionRecords as $record) {
        $session['attendance_records'][] = [
            'student_id' => $record->student_id,
            'arrival_time' => $record->arrival_time,
            'remarks' => $record->remarks,
            'attendance_status' => [
                'name' => $record->status_name,
                'code' => $record->status_code
            ]
        ];
    }

    $sessions[] = $session;
}

echo "Sessions after transformation: " . count($sessions) . "\n";
foreach ($sessions as $session) {
    echo "Session {$session['id']}: " . count($session['attendance_records']) . " records\n";
    foreach ($session['attendance_records'] as $record) {
        echo "  Student {$record['student_id']}: {$record['attendance_status']['name']}\n";
    }
}

?>
