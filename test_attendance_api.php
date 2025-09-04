<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Database configuration
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'database' => 'lamms_db',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== TESTING ATTENDANCE TABLE STRUCTURE ===\n\n";

try {
    // Check table structure
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'attendances' ORDER BY ordinal_position");
    
    echo "Attendance table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column->column_name} ({$column->data_type})\n";
    }
    
    echo "\n=== TESTING ATTENDANCE INSERTION ===\n\n";
    
    // Test attendance record insertion
    $attendanceData = [
        'student_id' => 3,
        'subject_id' => null,
        'date' => '2025-01-04',
        'status' => 'Present',
        'remarks' => 'Test attendance',
        'created_at' => now(),
        'updated_at' => now()
    ];
    
    // Check if record exists
    $existing = DB::table('attendances')
        ->where('student_id', 3)
        ->where('date', '2025-01-04')
        ->whereNull('subject_id')
        ->first();
    
    if ($existing) {
        echo "✓ Found existing attendance record: ID {$existing->id}\n";
        
        // Update existing record
        DB::table('attendances')
            ->where('id', $existing->id)
            ->update([
                'status' => 'Present',
                'remarks' => 'Updated test attendance',
                'updated_at' => now()
            ]);
        
        echo "✓ Updated existing attendance record\n";
    } else {
        // Insert new record
        $id = DB::table('attendances')->insertGetId($attendanceData);
        echo "✓ Created new attendance record with ID: $id\n";
    }
    
    echo "\n=== TESTING TEACHER ASSIGNMENT VERIFICATION ===\n\n";
    
    // Test teacher assignment verification
    $teacherAssignment = DB::table('teacher_section_subject')
        ->where('teacher_id', 1)
        ->where('section_id', 13)
        ->whereNull('subject_id')
        ->where('is_active', true)
        ->first();
    
    if ($teacherAssignment) {
        echo "✓ Teacher assignment verified: Teacher 1 -> Section 13 (Homeroom)\n";
        echo "  Assignment ID: {$teacherAssignment->id}\n";
        echo "  Role: {$teacherAssignment->role}\n";
    } else {
        echo "✗ Teacher assignment not found\n";
    }
    
    echo "\n=== TESTING STUDENT SECTION VERIFICATION ===\n\n";
    
    // Test student in section verification
    $studentInSection = DB::table('students')
        ->join('student_section', 'students.id', '=', 'student_section.student_id')
        ->where('students.id', 3)
        ->where('student_section.section_id', 13)
        ->where('student_section.is_active', true)
        ->first();
    
    if ($studentInSection) {
        echo "✓ Student in section verified: Student 3 -> Section 13\n";
        echo "  Student Name: {$studentInSection->name}\n";
    } else {
        echo "✗ Student not found in section\n";
    }
    
    echo "\n=== ATTENDANCE API TEST COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
