<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

Config::set('database.default', 'pgsql');
Config::set('database.connections.pgsql', [
    'driver' => 'pgsql',
    'host' => 'localhost',
    'port' => '5432',
    'database' => 'lamms_db',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

echo "Debugging attendance data for teacher 3...\n\n";

// Check attendance sessions for teacher 3
$sessions = DB::table('attendance_sessions')
    ->where('teacher_id', 3)
    ->get();

echo "Attendance sessions for teacher 3: " . count($sessions) . "\n";
foreach ($sessions as $session) {
    echo "  Session ID: {$session->id}, Section: {$session->section_id}, Subject: {$session->subject_id}, Date: {$session->session_date}\n";
    
    // Get records for this session
    $records = DB::table('attendance_records as ar')
        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
        ->where('ar.attendance_session_id', $session->id)
        ->select('ar.*', 'ast.name as status_name')
        ->get();
    
    foreach ($records as $record) {
        echo "    Student {$record->student_id}: {$record->status_name}\n";
    }
}

echo "\n";

// Test the actual query used in AttendanceSummaryController
echo "Testing absence count query with correct capitalization...\n";
$absenceQuery = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ases.teacher_id', 3)
    ->where('ast.name', 'Absent');

$absenceCount = $absenceQuery->count();
echo "Total absences for teacher 3: {$absenceCount}\n";

// Get the actual records
$absenceRecords = $absenceQuery->select('ar.student_id', 'ases.session_date', 'ast.name')->get();
foreach ($absenceRecords as $record) {
    echo "  Student {$record->student_id} absent on {$record->session_date}\n";
}

echo "\n";

// Test present count
$presentQuery = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ases.teacher_id', 3)
    ->where('ast.name', 'Present');

$presentCount = $presentQuery->count();
echo "Total present for teacher 3: {$presentCount}\n";

// Check attendance statuses table
echo "\nAttendance statuses:\n";
$statuses = DB::table('attendance_statuses')->get();
foreach ($statuses as $status) {
    echo "  ID: {$status->id}, Name: {$status->name}\n";
}
