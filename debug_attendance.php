<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Attendance Sessions Debug ===\n";

// Check attendance sessions for section 1
$sessions = DB::table('attendance_sessions')->where('section_id', 1)->get();
echo "Sessions for section 1: " . $sessions->count() . "\n";

foreach($sessions as $session) {
    echo "Session ID: {$session->id}, Date: {$session->session_date}, Subject: {$session->subject_id}\n";
    
    $records = DB::table('attendance_records')->where('attendance_session_id', $session->id)->get();
    echo "  Records: " . $records->count() . "\n";
    
    foreach($records as $record) {
        echo "    Student ID: {$record->student_id}, Status: {$record->attendance_status_id}\n";
    }
}

// Check if there are any attendance records at all
echo "\n=== All Attendance Records ===\n";
$allRecords = DB::table('attendance_records')->get();
echo "Total attendance records: " . $allRecords->count() . "\n";

foreach($allRecords as $record) {
    echo "Record ID: {$record->id}, Session: {$record->attendance_session_id}, Student: {$record->student_id}, Status: {$record->attendance_status_id}\n";
}

?>
