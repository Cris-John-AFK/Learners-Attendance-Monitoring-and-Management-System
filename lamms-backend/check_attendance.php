<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking attendance records for session 2...\n\n";

// Get all attendance records with student names
$records = DB::table('attendance_records as ar')
    ->join('students as s', 'ar.student_id', '=', 's.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ar.attendance_session_id', 2)
    ->where('ar.is_current_version', 1)
    ->select('s.id', 's.firstName', 's.lastName', 'ast.code', 'ast.name')
    ->orderBy('s.lastName')
    ->get();

echo "Total records: " . $records->count() . "\n\n";

$presentCount = 0;
$absentCount = 0;

foreach ($records as $record) {
    $status = $record->code === 'P' ? 'PRESENT' : ($record->code === 'A' ? 'ABSENT' : $record->code);
    echo sprintf("%-25s (ID: %3d) - %s\n", $record->firstName . ' ' . $record->lastName, $record->id, $status);
    
    if ($record->code === 'P') $presentCount++;
    if ($record->code === 'A') $absentCount++;
}

echo "\nSummary:\n";
echo "Present: $presentCount\n";
echo "Absent: $absentCount\n";
