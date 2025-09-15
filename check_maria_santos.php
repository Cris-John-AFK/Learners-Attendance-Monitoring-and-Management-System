<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // Query Maria Santos teacher data with homeroom assignment
    $teacher = DB::table('teachers')
        ->leftJoin('teacher_assignments as ta', function($join) {
            $join->on('teachers.id', '=', 'ta.teacher_id')
                 ->where('ta.is_primary', true);
        })
        ->leftJoin('sections', 'ta.section_id', '=', 'sections.id')
        ->leftJoin('grades', 'sections.grade_id', '=', 'grades.id')
        ->where('teachers.first_name', 'Maria')
        ->where('teachers.last_name', 'Santos')
        ->select([
            'teachers.*',
            'sections.name as section_name',
            'sections.id as section_id',
            'grades.name as grade_name',
            'grades.id as grade_id',
            'ta.is_primary'
        ])
        ->first();
    
    if ($teacher) {
        echo "Maria Santos found:\n";
        echo "ID: " . $teacher->id . "\n";
        echo "Name: " . $teacher->first_name . " " . $teacher->last_name . "\n";
        echo "Email: " . $teacher->email . "\n";
        echo "Homeroom Section: " . ($teacher->section_name ?: 'None assigned') . "\n";
        echo "Grade: " . ($teacher->grade_name ?: 'N/A') . "\n";
        echo "Is Primary: " . ($teacher->is_primary ? 'Yes' : 'No') . "\n";
        
        // Also check all assignments
        $assignments = DB::table('teacher_assignments')
            ->join('sections', 'teacher_assignments.section_id', '=', 'sections.id')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->leftJoin('subjects', 'teacher_assignments.subject_id', '=', 'subjects.id')
            ->where('teacher_assignments.teacher_id', $teacher->id)
            ->select([
                'teacher_assignments.*',
                'sections.name as section_name',
                'grades.name as grade_name',
                'subjects.name as subject_name'
            ])
            ->get();
            
        echo "\nAll assignments:\n";
        foreach ($assignments as $assignment) {
            echo "- Section: " . $assignment->section_name . 
                 " (" . $assignment->grade_name . ")" .
                 ", Subject: " . ($assignment->subject_name ?: 'N/A') .
                 ", Primary: " . ($assignment->is_primary ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "Maria Santos not found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
