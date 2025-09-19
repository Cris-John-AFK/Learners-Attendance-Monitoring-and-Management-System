<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Section 2 Attendance ===\n";

// Check what sections exist
echo "All sections:\n";
$sections = DB::table('sections')->get();
foreach($sections as $section) {
    echo "  Section ID: {$section->id}, Name: {$section->name}\n";
}

// Check attendance sessions for section 2
echo "\nAttendance sessions for section 2:\n";
$sessions = DB::table('attendance_sessions')->where('section_id', 2)->get();
echo "Sessions found: " . $sessions->count() . "\n";
foreach($sessions as $session) {
    echo "  Session ID: {$session->id}, Date: {$session->session_date}, Subject: {$session->subject_id}, Status: {$session->status}\n";
    
    // Check records for this session
    $records = DB::table('attendance_records')->where('attendance_session_id', $session->id)->get();
    echo "    Records: " . $records->count() . "\n";
    foreach($records as $record) {
        echo "      Student {$record->student_id}: Status {$record->attendance_status_id}\n";
    }
}

// Check most recent sessions
echo "\nMost recent attendance sessions (all sections):\n";
$recentSessions = DB::table('attendance_sessions')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();
    
foreach($recentSessions as $session) {
    echo "  Session ID: {$session->id}, Section: {$session->section_id}, Date: {$session->session_date}, Subject: {$session->subject_id}, Created: {$session->created_at}\n";
}

?>
