<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING FOR INVALID STUDENT DATA ===\n\n";

// 1. Check for students with ID "STU20252706126Z3"
echo "1. Searching for problematic student ID 'STU20252706126Z3':\n";
$problematicStudent = DB::table('student_details')->where('studentId', 'STU20252706126Z3')->first();
if ($problematicStudent) {
    echo "   FOUND: Student ID {$problematicStudent->id}, Name: {$problematicStudent->firstName} {$problematicStudent->lastName}\n";
    echo "   Student ID: {$problematicStudent->studentId}\n";
    echo "   Active: " . ($problematicStudent->isActive ? 'Yes' : 'No') . "\n";
} else {
    echo "   Not found in student_details table\n";
}

// 2. Check all students in section 3 (Malikhain)
echo "\n2. All students enrolled in section 3 (Malikhain):\n";
$studentsInSection = DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->where('ss.section_id', 3)
    ->where('ss.is_active', true)
    ->select('sd.*', 'ss.status as enrollment_status')
    ->get();

foreach ($studentsInSection as $student) {
    echo "   ID: {$student->id}, Name: {$student->firstName} {$student->lastName}\n";
    echo "   Student ID: {$student->studentId}\n";
    echo "   Status: {$student->enrollment_status}\n";
    echo "   Active: " . ($student->isActive ? 'Yes' : 'No') . "\n";
    echo "   ---\n";
}

// 3. Check for any students with unusual or test data patterns
echo "\n3. Students with suspicious patterns:\n";
$suspiciousStudents = DB::table('student_details')
    ->where(function($query) {
        $query->where('firstName', 'like', '%test%')
              ->orWhere('lastName', 'like', '%test%')
              ->orWhere('firstName', 'like', '%STU%')
              ->orWhere('lastName', 'like', '%STU%')
              ->orWhere('studentId', 'like', '%STU%')
              ->orWhere('firstName', '=', '')
              ->orWhere('lastName', '=', '');
    })
    ->get();

if ($suspiciousStudents->count() > 0) {
    foreach ($suspiciousStudents as $student) {
        echo "   SUSPICIOUS: ID {$student->id}, Name: '{$student->firstName}' '{$student->lastName}'\n";
        echo "   Student ID: {$student->studentId}\n";
        echo "   Active: " . ($student->isActive ? 'Yes' : 'No') . "\n";
        echo "   ---\n";
    }
} else {
    echo "   No suspicious student patterns found\n";
}

// 4. Check student_section enrollments for invalid data
echo "\n4. Student-section enrollments with potential issues:\n";
$invalidEnrollments = DB::table('student_section as ss')
    ->leftJoin('student_details as sd', 'ss.student_id', '=', 'sd.id')
    ->leftJoin('sections as s', 'ss.section_id', '=', 's.id')
    ->whereNull('sd.id')  // Student doesn't exist
    ->orWhereNull('s.id') // Section doesn't exist
    ->select('ss.*', 'sd.firstName', 'sd.lastName', 's.name as section_name')
    ->get();

if ($invalidEnrollments->count() > 0) {
    foreach ($invalidEnrollments as $enrollment) {
        echo "   INVALID ENROLLMENT: Student ID {$enrollment->student_id}, Section ID {$enrollment->section_id}\n";
        echo "   Student exists: " . ($enrollment->firstName ? 'Yes' : 'No') . "\n";
        echo "   Section exists: " . ($enrollment->section_name ? 'Yes' : 'No') . "\n";
        echo "   ---\n";
    }
} else {
    echo "   All enrollments appear valid\n";
}

echo "\n=== CLEANUP RECOMMENDATIONS ===\n";
if ($problematicStudent) {
    echo "RECOMMENDED ACTION: Remove student with ID 'STU20252706126Z3'\n";
    echo "SQL: DELETE FROM student_details WHERE studentId = 'STU20252706126Z3';\n";
}

if ($suspiciousStudents->count() > 0) {
    echo "RECOMMENDED ACTION: Review and clean up suspicious student records\n";
}

echo "\n=== END CHECK ===\n";
