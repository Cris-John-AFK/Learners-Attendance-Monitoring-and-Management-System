<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\Student;
use App\Models\Section;

echo "=== TEACHER SYSTEM ANALYSIS ===\n\n";

// Check teachers
echo "1. TEACHERS IN DATABASE:\n";
$teachers = Teacher::with('user')->get();
echo "Total teachers: " . $teachers->count() . "\n\n";

foreach ($teachers->take(5) as $teacher) {
    echo "ID: {$teacher->id} - {$teacher->first_name} {$teacher->last_name}";
    if ($teacher->user) {
        echo " (Email: {$teacher->user->email})";
    }
    echo "\n";
}

echo "\n2. TEACHER ASSIGNMENTS:\n";
$assignments = TeacherSectionSubject::with(['teacher', 'section', 'subject'])->take(10)->get();
echo "Total assignments: " . TeacherSectionSubject::count() . "\n\n";

foreach ($assignments as $assignment) {
    echo "Teacher: " . ($assignment->teacher->first_name ?? 'N/A') . " " . ($assignment->teacher->last_name ?? 'N/A');
    echo " | Section: " . ($assignment->section->name ?? 'N/A');
    echo " | Subject: " . ($assignment->subject->name ?? 'N/A');
    echo " | Role: " . ($assignment->role ?? 'N/A') . "\n";
}

echo "\n3. STUDENTS IN SECTIONS:\n";
$studentsInSections = Student::whereHas('sections')->with('sections')->take(5)->get();
echo "Students with sections: " . Student::whereHas('sections')->count() . "\n\n";

foreach ($studentsInSections as $student) {
    $section = $student->sections->first();
    echo "Student: {$student->firstName} {$student->lastName} | Section: " . ($section->name ?? 'N/A') . "\n";
}

echo "\n4. SECTIONS WITH STUDENTS:\n";
$sectionsWithStudents = Section::with('students')->get()->filter(function($section) {
    return $section->students->count() > 0;
});
echo "Sections with students: " . $sectionsWithStudents->count() . "\n\n";

foreach ($sectionsWithStudents as $section) {
    echo "Section: {$section->name} | Students: {$section->students->count()}\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
