<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "Checking Maria Santos...\n";
    
    // Query Maria Santos basic info
    $teacher = DB::table('teachers')
        ->where('first_name', 'Maria')
        ->where('last_name', 'Santos')
        ->first();
    
    if ($teacher) {
        echo "Maria Santos found:\n";
        echo "ID: " . $teacher->id . "\n";
        echo "Name: " . $teacher->first_name . " " . $teacher->last_name . "\n";
        echo "Username: " . ($teacher->username ?? 'N/A') . "\n";
        
        // Check teacher_section_subject table for assignments
        echo "\nChecking teacher_section_subject assignments...\n";
        $assignments = DB::table('teacher_section_subject as tss')
            ->join('sections as s', 'tss.section_id', '=', 's.id')
            ->join('grades as g', 's.grade_id', '=', 'g.id')
            ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
            ->where('tss.teacher_id', $teacher->id)
            ->select([
                'tss.*',
                's.name as section_name',
                'g.name as grade_name',
                'sub.name as subject_name'
            ])
            ->get();
        
        if ($assignments->count() > 0) {
            echo "Found " . $assignments->count() . " assignments:\n";
            foreach ($assignments as $assignment) {
                echo "- Section: " . $assignment->section_name . 
                     " (" . $assignment->grade_name . ")" .
                     ", Subject: " . ($assignment->subject_name ?: 'N/A') .
                     ", Role: " . ($assignment->role ?? 'N/A') . "\n";
            }
        } else {
            echo "No assignments found\n";
        }
        
    } else {
        echo "Maria Santos not found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
