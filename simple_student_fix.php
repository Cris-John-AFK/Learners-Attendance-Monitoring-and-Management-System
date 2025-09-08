<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== SIMPLE STUDENT DATA FIX ===\n\n";

try {
    DB::beginTransaction();
    
    // 1. Remove the test student "g3 g3" if it exists
    echo "1. Checking for test student 'g3 g3':\n";
    $testStudent = DB::table('student_details')->where('firstName', 'g3')->where('lastName', 'g3')->first();
    
    if ($testStudent) {
        echo "   Found test student ID: {$testStudent->id}\n";
        
        // Remove enrollments
        DB::table('student_section')->where('student_id', $testStudent->id)->delete();
        echo "   Removed section enrollments\n";
        
        // Remove attendance
        DB::table('attendances')->where('student_id', $testStudent->id)->delete();
        echo "   Removed attendance records\n";
        
        // Remove student
        DB::table('student_details')->where('id', $testStudent->id)->delete();
        echo "   Removed student record\n";
    } else {
        echo "   No test student found\n";
    }
    
    // 2. Create one proper student with all required fields
    echo "\n2. Creating proper student:\n";
    
    $studentId = DB::table('student_details')->insertGetId([
        'studentId' => 'STU2025001001',
        'student_id' => 'STU2025001001', // This might be the required field
        'firstName' => 'Juan',
        'lastName' => 'Dela Cruz',
        'middleName' => 'Santos',
        'lrn' => '123456789012',
        'gradeLevel' => 3,
        'gender' => 'Male',
        'age' => 8,
        'isActive' => true,
        'status' => 'enrolled',
        'enrollmentDate' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "   Created student: Juan Dela Cruz (ID: {$studentId})\n";
    
    // 3. Enroll in Malikhain section
    DB::table('student_section')->insert([
        'student_id' => $studentId,
        'section_id' => 3,
        'is_active' => true,
        'status' => 'enrolled',
        'enrollment_date' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "   Enrolled in Malikhain section\n";
    
    DB::commit();
    echo "\n✅ Fix completed successfully!\n";
    
    // 4. Verify
    echo "\n3. Current students in Malikhain:\n";
    $students = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->where('ss.section_id', 3)
        ->where('ss.is_active', true)
        ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.studentId')
        ->get();
    
    foreach ($students as $student) {
        echo "   ✓ {$student->firstName} {$student->lastName} (ID: {$student->id})\n";
    }
    
} catch (Exception $e) {
    DB::rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== END FIX ===\n";
