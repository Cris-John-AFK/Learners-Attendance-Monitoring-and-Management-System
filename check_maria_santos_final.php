<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "Checking Maria Santos and database structure...\n";
    
    // First check sections table structure
    echo "\nSections table structure:\n";
    $sectionColumns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'sections'");
    foreach ($sectionColumns as $col) {
        echo "- " . $col->column_name . "\n";
    }
    
    // Query Maria Santos basic info
    $teacher = DB::table('teachers')
        ->where('first_name', 'Maria')
        ->where('last_name', 'Santos')
        ->first();
    
    if ($teacher) {
        echo "\nMaria Santos found:\n";
        echo "ID: " . $teacher->id . "\n";
        echo "Name: " . $teacher->first_name . " " . $teacher->last_name . "\n";
        
        // Check teacher_section_subject table for assignments (simplified query)
        echo "\nChecking teacher_section_subject assignments...\n";
        $assignments = DB::table('teacher_section_subject as tss')
            ->join('sections as s', 'tss.section_id', '=', 's.id')
            ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
            ->where('tss.teacher_id', $teacher->id)
            ->select([
                'tss.*',
                's.name as section_name',
                'sub.name as subject_name'
            ])
            ->get();
        
        if ($assignments->count() > 0) {
            echo "Found " . $assignments->count() . " assignments:\n";
            foreach ($assignments as $assignment) {
                echo "- Section: " . $assignment->section_name . 
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
