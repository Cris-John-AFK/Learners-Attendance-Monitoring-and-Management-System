<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Today's Attendance (Sep 19, 2025) - FIXED ===\n";

// Check attendance sessions for today
echo "Attendance sessions for 2025-09-19:\n";
$todaySessions = DB::table('attendance_sessions as as')
    ->leftJoin('teachers as t', 'as.teacher_id', '=', 't.id')
    ->leftJoin('sections as s', 'as.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'as.subject_id', '=', 'sub.id')
    ->where('as.session_date', '2025-09-19')
    ->select('as.*', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name')
    ->get();

if ($todaySessions->isEmpty()) {
    echo "  ❌ No sessions found for today (2025-09-19)\n";
} else {
    foreach($todaySessions as $session) {
        echo "  ✅ Session {$session->id}: {$session->first_name} {$session->last_name} - {$session->section_name} - {$session->subject_name} - Status: {$session->status}\n";
    }
}

// Check attendance records for today's sessions (simplified query)
echo "\nAttendance records for today's sessions:\n";
if (!$todaySessions->isEmpty()) {
    foreach($todaySessions as $session) {
        $records = DB::table('attendance_records')
            ->where('attendance_session_id', $session->id)
            ->get();
        
        echo "  Session {$session->id} records:\n";
        if ($records->isEmpty()) {
            echo "    ❌ No attendance records found for session {$session->id}\n";
        } else {
            foreach($records as $record) {
                echo "    - Student ID: {$record->student_id}, Status ID: {$record->attendance_status_id}, Marked: {$record->marked_at}\n";
            }
        }
    }
} else {
    echo "  No sessions to check records for\n";
}

// Check what students are in the section
echo "\nStudents in Kinder One (Section 1):\n";
$students = DB::table('student_section as ss')
    ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
    ->where('ss.section_id', 1)
    ->select('sd.id', 'sd.name')
    ->get();

foreach($students as $student) {
    echo "  - Student {$student->id}: {$student->name}\n";
}

// Check if the attendance records API is working
echo "\nTesting Attendance Records API:\n";
try {
    // Test the specific endpoint that the frontend might be using
    $endpoints = [
        '/api/attendance-records/teacher/1',
        '/api/teachers/1/attendance-records',
        '/api/attendance-records/section/1'
    ];
    
    foreach($endpoints as $endpoint) {
        echo "\nTesting endpoint: {$endpoint}\n";
        try {
            $response = file_get_contents("http://127.0.0.1:8000{$endpoint}", false, stream_context_create([
                'http' => [
                    'header' => 'Accept: application/json'
                ]
            ]));
            
            $apiData = json_decode($response, true);
            if ($apiData) {
                echo "  ✅ API responded successfully\n";
                if (isset($apiData['records'])) {
                    echo "  Records count: " . count($apiData['records']) . "\n";
                } elseif (isset($apiData['sessions'])) {
                    echo "  Sessions count: " . count($apiData['sessions']) . "\n";
                } else {
                    echo "  Response keys: " . implode(', ', array_keys($apiData)) . "\n";
                }
            } else {
                echo "  ❌ Invalid JSON response\n";
            }
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>
