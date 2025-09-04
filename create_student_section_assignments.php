<?php

require_once 'lamms-backend/vendor/autoload.php';
require_once 'lamms-backend/bootstrap/app.php';

use App\Models\Student;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

echo "=== CREATING STUDENT-SECTION ASSIGNMENTS ===\n\n";

try {
    // Get all students
    $students = Student::all();
    echo "Found {$students->count()} students\n";
    
    // Get sections that have teacher assignments
    $sectionsWithTeachers = DB::table('teacher_section_subject')
        ->join('sections', 'teacher_section_subject.section_id', '=', 'sections.id')
        ->where('teacher_section_subject.is_active', true)
        ->select('sections.id', 'sections.name')
        ->distinct()
        ->get();
    
    echo "Found {$sectionsWithTeachers->count()} sections with teacher assignments\n\n";
    
    // Assign students to sections
    $assignments = [
        // Student 3 (awdawd awd) -> Section 13 (Malikhain) - Homeroom with Teacher 1
        3 => 13,
        // Student 2 (awdawd awdawd) -> Section 14 (Mapagmahal) - Science with Teacher 1  
        2 => 14,
        // Student 5 (dawd ki) -> Section 72 (Kn2) - English with Teacher 1
        5 => 72,
        // Additional assignments for remaining students
        1 => 13, // Assign to Malikhain
        4 => 14  // Assign to Mapagmahal
    ];
    
    foreach ($assignments as $studentId => $sectionId) {
        // Check if student exists
        $student = Student::find($studentId);
        if (!$student) {
            echo "⚠ Student $studentId not found, skipping\n";
            continue;
        }
        
        // Check if section exists
        $section = Section::find($sectionId);
        if (!$section) {
            echo "⚠ Section $sectionId not found, skipping\n";
            continue;
        }
        
        // Create or update student-section assignment
        $existing = DB::table('student_section')
            ->where('student_id', $studentId)
            ->where('section_id', $sectionId)
            ->first();
        
        if ($existing) {
            // Update existing assignment to active
            DB::table('student_section')
                ->where('student_id', $studentId)
                ->where('section_id', $sectionId)
                ->update([
                    'is_active' => true,
                    'updated_at' => now()
                ]);
            echo "✓ Updated assignment: {$student->name} -> {$section->name}\n";
        } else {
            // Create new assignment
            DB::table('student_section')->insert([
                'student_id' => $studentId,
                'section_id' => $sectionId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "✓ Created assignment: {$student->name} -> {$section->name}\n";
        }
    }
    
    echo "\n=== VERIFICATION ===\n";
    
    // Verify assignments
    $activeAssignments = DB::table('student_section')
        ->join('students', 'student_section.student_id', '=', 'students.id')
        ->join('sections', 'student_section.section_id', '=', 'sections.id')
        ->where('student_section.is_active', true)
        ->select('students.id as student_id', 'students.name as student_name', 
                'sections.id as section_id', 'sections.name as section_name')
        ->get();
    
    echo "Active student-section assignments:\n";
    foreach ($activeAssignments as $assignment) {
        echo "- Student {$assignment->student_id} ({$assignment->student_name}) -> Section {$assignment->section_id} ({$assignment->section_name})\n";
    }
    
    echo "\n=== STUDENT-SECTION ASSIGNMENTS COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
