<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Today's Attendance (Sep 19, 2025) ===\n";

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

// Check attendance records for today's sessions
echo "\nAttendance records for today's sessions:\n";
if (!$todaySessions->isEmpty()) {
    foreach($todaySessions as $session) {
        $records = DB::table('attendance_records as ar')
            ->leftJoin('student_details as sd', 'ar.student_id', '=', 'sd.id')
            ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->where('ar.attendance_session_id', $session->id)
            ->select('ar.*', 'sd.name as student_name', 'ast.status_name')
            ->get();
        
        echo "  Session {$session->id} records:\n";
        if ($records->isEmpty()) {
            echo "    ❌ No attendance records found\n";
        } else {
            foreach($records as $record) {
                echo "    - Student: {$record->student_name}, Status: {$record->status_name}\n";
            }
        }
    }
} else {
    echo "  No sessions to check records for\n";
}

// Check what the attendance records API returns for Maria Santos
echo "\nTesting Attendance Records API for Maria Santos (Teacher 1):\n";
try {
    $response = file_get_contents('http://127.0.0.1:8000/api/attendance-records/teacher/1', false, stream_context_create([
        'http' => [
            'header' => 'Accept: application/json'
        ]
    ]));
    
    $apiData = json_decode($response, true);
    if ($apiData && isset($apiData['records'])) {
        echo "API returned " . count($apiData['records']) . " records\n";
        
        // Check for today's records
        $todayRecords = array_filter($apiData['records'], function($record) {
            return strpos($record['session_date'], '2025-09-19') !== false;
        });
        
        if (empty($todayRecords)) {
            echo "  ❌ No records found for today (2025-09-19) in API response\n";
        } else {
            echo "  ✅ Found " . count($todayRecords) . " records for today\n";
            foreach($todayRecords as $record) {
                echo "    - {$record['student_name']}: {$record['status']} on {$record['session_date']}\n";
            }
        }
    } else {
        echo "API Error: " . $response . "\n";
    }
} catch (Exception $e) {
    echo "Error calling API: " . $e->getMessage() . "\n";
}

// Check the most recent session
echo "\nMost recent attendance session:\n";
$latestSession = DB::table('attendance_sessions as as')
    ->leftJoin('teachers as t', 'as.teacher_id', '=', 't.id')
    ->leftJoin('sections as s', 'as.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'as.subject_id', '=', 'sub.id')
    ->orderBy('as.created_at', 'desc')
    ->select('as.*', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name')
    ->first();

if ($latestSession) {
    echo "  Latest: Session {$latestSession->id} - {$latestSession->first_name} {$latestSession->last_name} - {$latestSession->section_name} - {$latestSession->subject_name} - {$latestSession->session_date} - Created: {$latestSession->created_at}\n";
} else {
    echo "  No sessions found\n";
}

?>
