<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\Student;
use App\Models\Section;
use App\Models\Subject;

echo "=== CREATING TEACHER ASSIGNMENTS ===\n\n";

// Get sections with students
$sectionsWithStudents = Section::with('students')->get()->filter(function($section) {
    return $section->students->count() > 0;
});

echo "Found " . $sectionsWithStudents->count() . " sections with students\n";

// Get available teachers
$teachers = Teacher::limit(5)->get();
echo "Using " . $teachers->count() . " teachers for assignments\n\n";

// Get or create basic subjects
$subjects = [];
$subjectNames = ['Mathematics', 'English', 'Science', 'Filipino', 'Social Studies'];

foreach ($subjectNames as $index => $subjectName) {
    $subject = Subject::firstOrCreate([
        'name' => $subjectName
    ], [
        'code' => strtoupper(substr($subjectName, 0, 3)) . ($index + 1),
        'description' => $subjectName . ' subject',
        'is_active' => true
    ]);
    $subjects[] = $subject;
    echo "Subject: {$subject->name} (ID: {$subject->id})\n";
}

echo "\n=== CREATING ASSIGNMENTS ===\n\n";

$assignmentCount = 0;
$teacherIndex = 0;

foreach ($sectionsWithStudents as $section) {
    echo "Section: {$section->name} (Students: {$section->students->count()})\n";
    
    // Assign 2-3 subjects per section
    $sectionSubjects = array_slice($subjects, 0, 3);
    
    foreach ($sectionSubjects as $subject) {
        $teacher = $teachers[$teacherIndex % $teachers->count()];
        
        // Create teacher assignment
        $assignment = TeacherSectionSubject::create([
            'teacher_id' => $teacher->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'role' => $assignmentCount == 0 ? 'homeroom' : 'subject',
            'is_primary' => $assignmentCount == 0,
            'is_active' => true
        ]);
        
        echo "  - Assigned {$teacher->first_name} {$teacher->last_name} to {$subject->name} (Role: {$assignment->role})\n";
        
        $assignmentCount++;
        $teacherIndex++;
    }
    echo "\n";
}

echo "=== ASSIGNMENT SUMMARY ===\n";
echo "Total assignments created: " . TeacherSectionSubject::count() . "\n";

// Show assignments for verification
echo "\n=== VERIFICATION ===\n";
$assignments = TeacherSectionSubject::with(['teacher', 'section', 'subject'])->get();

foreach ($assignments as $assignment) {
    echo "Teacher: " . $assignment->teacher->first_name . " " . $assignment->teacher->last_name;
    echo " | Section: " . $assignment->section->name;
    echo " | Subject: " . $assignment->subject->name;
    echo " | Role: " . $assignment->role . "\n";
}

echo "\n=== COMPLETE ===\n";
