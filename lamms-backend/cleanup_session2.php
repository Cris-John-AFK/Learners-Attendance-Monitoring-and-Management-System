<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Cleaning up session 2 attendance records...\n\n";

// Get valid students who are ACTUALLY in section 167
$validStudents = DB::table('student_section')
    ->where('section_id', 167)
    ->where('is_active', 1)
    ->pluck('student_id')
    ->toArray();

echo "Valid students in section 167: " . count($validStudents) . "\n";
echo "Student IDs: " . implode(', ', $validStudents) . "\n\n";

// Count invalid records before deletion
$invalidCount = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->whereNotIn('student_id', $validStudents)
    ->count();

echo "Invalid records to delete: $invalidCount\n\n";

// Delete invalid records
$deleted = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->whereNotIn('student_id', $validStudents)
    ->delete();

echo "Deleted: $deleted invalid attendance records\n\n";

// Get final counts
$totalRecords = DB::table('attendance_records')
    ->where('attendance_session_id', 2)
    ->count();

$present = DB::table('attendance_records as ar')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ar.attendance_session_id', 2)
    ->where('ast.code', 'P')
    ->count();

$absent = DB::table('attendance_records as ar')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ar.attendance_session_id', 2)
    ->where('ast.code', 'A')
    ->count();

$late = DB::table('attendance_records as ar')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ar.attendance_session_id', 2)
    ->where('ast.code', 'L')
    ->count();

echo "=== FINAL COUNTS ===\n";
echo "Total records: $totalRecords\n";
echo "Present: $present\n";
echo "Absent: $absent\n";
echo "Late: $late\n";

// Calculate attendance rate
$attendanceRate = $totalRecords > 0 ? round((($present + $late) / $totalRecords) * 100, 1) : 0;
echo "Attendance Rate: $attendanceRate%\n\n";

echo "✅ Session 2 cleaned up successfully!\n";
