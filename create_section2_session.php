<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Creating Attendance Session for Section 2 ===\n";

// Create attendance session for section 2
$sessionId = DB::table('attendance_sessions')->insertGetId([
    'teacher_id' => 1,
    'section_id' => 2, // Kinder Two
    'subject_id' => 3, // Filipino
    'session_date' => '2025-09-17',
    'session_start_time' => '22:45:00',
    'session_end_time' => '22:47:00',
    'session_type' => 'regular',
    'status' => 'completed',
    'metadata' => '[]',
    'created_at' => now(),
    'updated_at' => now(),
    'completed_at' => now(),
    'version' => 1,
    'is_current_version' => true
]);

echo "Created attendance session ID: $sessionId\n";

// Create attendance records for the two students in section 2
$students = DB::table('student_section')
    ->where('section_id', 2)
    ->where('is_active', true)
    ->get();

echo "Students in section 2: " . $students->count() . "\n";

foreach($students as $student) {
    // Alternate between Present (1) and Absent (2) for demo
    $statusId = $student->student_id == 15 ? 1 : 2; // Student 15 Present, Student 17 Absent
    
    $recordId = DB::table('attendance_records')->insertGetId([
        'attendance_session_id' => $sessionId,
        'student_id' => $student->student_id,
        'attendance_status_id' => $statusId,
        'marked_by_teacher_id' => 1,
        'marked_at' => now(),
        'arrival_time' => now()->format('H:i:s'),
        'marking_method' => 'manual',
        'marked_from_ip' => '127.0.0.1',
        'is_verified' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'version' => 1,
        'is_current_version' => true,
        'data_source' => 'manual'
    ]);
    
    $statusName = $statusId == 1 ? 'Present' : 'Absent';
    echo "Created attendance record for student {$student->student_id}: $statusName (Record ID: $recordId)\n";
}

echo "\nAttendance session and records created successfully!\n";

?>
