<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Teacher Sessions ===\n";

// Check all teachers
echo "All teachers:\n";
$teachers = DB::table('teachers')->get();
foreach($teachers as $teacher) {
    echo "  Teacher ID: {$teacher->id}, Name: {$teacher->first_name} {$teacher->last_name}\n";
}

// Check all attendance sessions
echo "\nAll attendance sessions:\n";
$sessions = DB::table('attendance_sessions')->get();
foreach($sessions as $session) {
    echo "  Session ID: {$session->id}, Teacher: {$session->teacher_id}, Section: {$session->section_id}, Date: {$session->session_date}\n";
}

// Check teacher assignments
echo "\nTeacher assignments:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->select('tss.teacher_id', 's.name as section_name', 'sub.name as subject_name', 'tss.section_id', 'tss.subject_id')
    ->get();
    
foreach($assignments as $assignment) {
    echo "  Teacher {$assignment->teacher_id}: {$assignment->section_name} - {$assignment->subject_name}\n";
}

// Update sessions to be accessible by teacher 3
echo "\nUpdating sessions to be accessible by teacher 3...\n";

// Check if teacher 3 exists, if not create one
$teacher3 = DB::table('teachers')->where('id', 3)->first();
if (!$teacher3) {
    echo "Teacher 3 doesn't exist, creating...\n";
    // Create a user first
    $userId = DB::table('users')->insertGetId([
        'username' => 'teacher3',
        'email' => 'teacher3@school.com',
        'password' => bcrypt('password'),
        'role' => 'teacher',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Create teacher record
    DB::table('teachers')->insert([
        'id' => 3,
        'user_id' => $userId,
        'first_name' => 'Teacher',
        'last_name' => 'Three',
        'is_head_teacher' => false,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "Created teacher 3\n";
}

// Create teacher assignments for teacher 3 to both sections
$existingAssignment1 = DB::table('teacher_section_subject')
    ->where('teacher_id', 3)
    ->where('section_id', 1)
    ->where('subject_id', 3)
    ->first();

if (!$existingAssignment1) {
    DB::table('teacher_section_subject')->insert([
        'teacher_id' => 3,
        'section_id' => 1,
        'subject_id' => 3,
        'role' => 'primary',
        'is_primary' => true,
        'is_active' => true
    ]);
    echo "Created assignment for teacher 3 to section 1\n";
}

$existingAssignment2 = DB::table('teacher_section_subject')
    ->where('teacher_id', 3)
    ->where('section_id', 2)
    ->where('subject_id', 3)
    ->first();

if (!$existingAssignment2) {
    DB::table('teacher_section_subject')->insert([
        'teacher_id' => 3,
        'section_id' => 2,
        'subject_id' => 3,
        'role' => 'primary',
        'is_primary' => true,
        'is_active' => true
    ]);
    echo "Created assignment for teacher 3 to section 2\n";
}

// Update existing sessions to also be accessible by teacher 3 (or create new ones for teacher 3)
$sessions = DB::table('attendance_sessions')->get();
foreach($sessions as $session) {
    // Create a duplicate session for teacher 3
    $newSessionId = DB::table('attendance_sessions')->insertGetId([
        'teacher_id' => 3,
        'section_id' => $session->section_id,
        'subject_id' => $session->subject_id,
        'session_date' => $session->session_date,
        'session_start_time' => $session->session_start_time,
        'session_end_time' => $session->session_end_time,
        'session_type' => $session->session_type,
        'status' => $session->status,
        'metadata' => $session->metadata,
        'created_at' => now(),
        'updated_at' => now(),
        'completed_at' => $session->completed_at,
        'version' => 1,
        'is_current_version' => true
    ]);
    
    echo "Created session {$newSessionId} for teacher 3 (copy of session {$session->id})\n";
    
    // Copy attendance records
    $records = DB::table('attendance_records')->where('attendance_session_id', $session->id)->get();
    foreach($records as $record) {
        DB::table('attendance_records')->insert([
            'attendance_session_id' => $newSessionId,
            'student_id' => $record->student_id,
            'attendance_status_id' => $record->attendance_status_id,
            'marked_by_teacher_id' => 3,
            'marked_at' => $record->marked_at,
            'arrival_time' => $record->arrival_time,
            'departure_time' => $record->departure_time,
            'remarks' => $record->remarks,
            'marking_method' => $record->marking_method,
            'marked_from_ip' => $record->marked_from_ip,
            'is_verified' => $record->is_verified,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
            'is_current_version' => true,
            'data_source' => $record->data_source
        ]);
    }
}

echo "\nCompleted! Teacher 3 should now see attendance sessions.\n";

?>
