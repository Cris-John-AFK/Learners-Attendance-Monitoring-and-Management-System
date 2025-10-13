<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING FOR DUPLICATE STUDENT NAMES ===\n";

// Check for students with similar names in section 226
$students = DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->where('ss.section_id', 226)
    ->where('ss.is_active', 1)
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
    ->orderBy('sd.firstName')
    ->orderBy('sd.lastName')
    ->get();

echo "All students in section 226:\n";
foreach ($students as $student) {
    echo "- {$student->firstName} {$student->lastName} (ID: {$student->id}) - Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
}

echo "\n=== LOOKING FOR ANGELO STUDENTS ===\n";
$angelos = DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->where('ss.section_id', 226)
    ->where('ss.is_active', 1)
    ->where('sd.firstName', 'LIKE', '%Angelo%')
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
    ->get();

foreach ($angelos as $student) {
    echo "Angelo found: {$student->firstName} {$student->lastName} (ID: {$student->id}) - Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
}

echo "\n=== LOOKING FOR ANDREA STUDENTS ===\n";
$andreas = DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->where('ss.section_id', 226)
    ->where('ss.is_active', 1)
    ->where('sd.firstName', 'LIKE', '%Andrea%')
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
    ->get();

foreach ($andreas as $student) {
    echo "Andrea found: {$student->firstName} {$student->lastName} (ID: {$student->id}) - Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
}

echo "\n=== CHECKING SPECIFIC IDs FROM YOUR IMAGE ===\n";
// Check the specific students you mentioned
$specificStudents = DB::table('student_details')
    ->whereIn('id', [3396, 3400, 3404, 3411]) // Angelo Vargas, Andres Chavez, Andrea Morales, Angelo Rivera
    ->select('id', 'firstName', 'lastName', 'enrollment_status')
    ->get();

foreach ($specificStudents as $student) {
    echo "ID {$student->id}: {$student->firstName} {$student->lastName} - Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
}
