<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Cleaning up attendance records for session 2...\n";

// Get valid students in section 167
$validStudents = DB::table('student_section')
    ->where('section_id', 167)
    ->where('is_active', 1)
    ->pluck('student_id')
    ->toArray();

echo "Valid students in section 167: " . count($validStudents) . "\n";

// Delete records for students NOT in this section
$deleted = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->whereNotIn('student_id', $validStudents)
    ->delete();

echo "Deleted $deleted invalid attendance records\n";

// Check final counts
$remaining = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->where('is_current_version', 1)
    ->count();

$present = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->where('is_current_version', 1)
    ->where('attendance_status_id', 1)
    ->count();

$absent = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->where('is_current_version', 1)
    ->where('attendance_status_id', 2)
    ->count();

echo "\nFinal counts:\n";
echo "Total records: $remaining\n";
echo "Present: $present\n";
echo "Absent: $absent\n";
echo "\nDone!\n";
