<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING STUDENT ENROLLMENT STATUSES ===\n";
$students = DB::table('student_details')
    ->select('id', 'firstName', 'lastName', 'enrollment_status')
    ->whereIn('id', [3404, 3411]) // Andrea and Angelo from the image
    ->get();

foreach ($students as $student) {
    echo "Student: {$student->firstName} {$student->lastName} (ID: {$student->id})\n";
    echo "Enrollment Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
    echo "---\n";
}

echo "\n=== ALL UNIQUE ENROLLMENT STATUSES ===\n";
$statuses = DB::table('student_details')
    ->select('enrollment_status')
    ->distinct()
    ->whereNotNull('enrollment_status')
    ->get();

foreach ($statuses as $status) {
    echo "'{$status->enrollment_status}'\n";
}

echo "\n=== SECTION 226 STUDENTS WITH STATUS ===\n";
$sectionStudents = DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->where('ss.section_id', 226)
    ->where('ss.is_active', 1)
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
    ->get();

foreach ($sectionStudents as $student) {
    echo "Student: {$student->firstName} {$student->lastName} (ID: {$student->id}) - Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
}
