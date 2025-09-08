<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CLEANING UP TEST STUDENT DATA ===\n\n";

try {
    DB::beginTransaction();
    
    // 1. Remove the test student "g3 g3"
    echo "1. Removing test student 'g3 g3' (ID: 7):\n";
    
    // First remove from student_section
    $sectionEnrollments = DB::table('student_section')->where('student_id', 7)->delete();
    echo "   Removed {$sectionEnrollments} section enrollment(s)\n";
    
    // Remove any attendance records
    $attendanceRecords = DB::table('attendances')->where('student_id', 7)->delete();
    echo "   Removed {$attendanceRecords} attendance record(s)\n";
    
    // Remove from student_details
    $studentDeleted = DB::table('student_details')->where('id', 7)->delete();
    echo "   Removed {$studentDeleted} student record(s)\n";
    
    // 2. Create proper student data for Grade 3 Malikhain section
    echo "\n2. Creating proper student data for Grade 3 Malikhain:\n";
    
    $students = [
        [
            'firstName' => 'Juan',
            'lastName' => 'Dela Cruz',
            'middleName' => 'Santos',
            'studentId' => 'STU2025001001',
            'lrn' => '123456789012',
            'gradeLevel' => 3,
            'gender' => 'Male',
            'age' => 8,
            'isActive' => true,
            'status' => 'enrolled',
            'enrollmentDate' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'firstName' => 'Maria',
            'lastName' => 'Santos',
            'middleName' => 'Garcia',
            'studentId' => 'STU2025001002',
            'lrn' => '123456789013',
            'gradeLevel' => 3,
            'gender' => 'Female',
            'age' => 8,
            'isActive' => true,
            'status' => 'enrolled',
            'enrollmentDate' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'firstName' => 'Pedro',
            'lastName' => 'Reyes',
            'middleName' => 'Cruz',
            'studentId' => 'STU2025001003',
            'lrn' => '123456789014',
            'gradeLevel' => 3,
            'gender' => 'Male',
            'age' => 9,
            'isActive' => true,
            'status' => 'enrolled',
            'enrollmentDate' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]
    ];
    
    foreach ($students as $studentData) {
        // Insert student
        $studentId = DB::table('student_details')->insertGetId($studentData);
        echo "   Created student: {$studentData['firstName']} {$studentData['lastName']} (ID: {$studentId})\n";
        
        // Enroll in Malikhain section (ID: 3)
        DB::table('student_section')->insert([
            'student_id' => $studentId,
            'section_id' => 3,
            'is_active' => true,
            'status' => 'enrolled',
            'enrollment_date' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "     Enrolled in Malikhain section\n";
    }
    
    DB::commit();
    echo "\n✅ Cleanup completed successfully!\n";
    
    // 3. Verify the cleanup
    echo "\n3. Verification - Students now in Malikhain section:\n";
    $currentStudents = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->where('ss.section_id', 3)
        ->where('ss.is_active', true)
        ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.studentId')
        ->get();
    
    foreach ($currentStudents as $student) {
        echo "   ✓ {$student->firstName} {$student->lastName} (ID: {$student->id}, Student ID: {$student->studentId})\n";
    }
    
} catch (Exception $e) {
    DB::rollback();
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}

echo "\n=== END CLEANUP ===\n";
