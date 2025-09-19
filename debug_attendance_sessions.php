<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Attendance Sessions ===\n";

// Check all attendance sessions in database
echo "All attendance sessions in database:\n";
$sessions = DB::table('attendance_sessions as as')
    ->leftJoin('teachers as t', 'as.teacher_id', '=', 't.id')
    ->leftJoin('sections as s', 'as.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'as.subject_id', '=', 'sub.id')
    ->select('as.*', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name')
    ->orderBy('as.created_at', 'desc')
    ->get();

foreach($sessions as $session) {
    echo "  Session {$session->id}: Teacher {$session->teacher_id} ({$session->first_name} {$session->last_name}) - {$session->section_name} - {$session->subject_name} - {$session->session_date}\n";
}

// Check teacher assignments
echo "\nTeacher assignments:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->select('t.id as teacher_id', 't.first_name', 't.last_name', 's.id as section_id', 's.name as section_name', 'sub.id as subject_id', 'sub.name as subject_name')
    ->orderBy('t.id')
    ->get();

foreach($assignments as $assignment) {
    echo "  Teacher {$assignment->teacher_id} ({$assignment->first_name} {$assignment->last_name}): Section {$assignment->section_id} ({$assignment->section_name}) - Subject {$assignment->subject_id} ({$assignment->subject_name})\n";
}

// Check what Maria Santos (Teacher 1) should see
echo "\nWhat Maria Santos (Teacher 1) should see:\n";
$mariaSections = DB::table('teacher_section_subject')
    ->where('teacher_id', 1)
    ->pluck('section_id')
    ->toArray();

echo "Maria's assigned sections: " . implode(', ', $mariaSections) . "\n";

$mariaSessions = DB::table('attendance_sessions as as')
    ->leftJoin('sections as s', 'as.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'as.subject_id', '=', 'sub.id')
    ->whereIn('as.section_id', $mariaSections)
    ->select('as.*', 's.name as section_name', 'sub.name as subject_name')
    ->get();

echo "Sessions Maria should see:\n";
foreach($mariaSessions as $session) {
    echo "  Session {$session->id}: {$session->section_name} - {$session->subject_name} - {$session->session_date}\n";
}

// Check current API endpoint
echo "\nTesting current API endpoint for teacher 1:\n";
$response = file_get_contents('http://127.0.0.1:8000/api/teachers/1/attendance-sessions', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$apiData = json_decode($response, true);
if ($apiData && isset($apiData['sessions'])) {
    echo "Current API returns " . count($apiData['sessions']) . " sessions:\n";
    foreach($apiData['sessions'] as $session) {
        echo "  Session {$session['id']}: {$session['section_name']} - {$session['subject_name']} - {$session['session_date']}\n";
    }
} else {
    echo "API Error or no sessions returned\n";
    echo "Response: " . $response . "\n";
}

?>
