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
    'password' => 'root',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== FIXING STUDENT-SECTION ASSIGNMENTS ===\n\n";

try {
    // Check current student-section relationships
    $studentSections = DB::table('student_section')
        ->join('students', 'student_section.student_id', '=', 'students.id')
        ->join('sections', 'student_section.section_id', '=', 'sections.id')
        ->select('students.id as student_id', 'students.name as student_name', 
                'sections.id as section_id', 'sections.name as section_name',
                'student_section.is_active')
        ->get();
    
    echo "Current student-section relationships:\n";
    foreach ($studentSections as $rel) {
        $status = $rel->is_active ? 'ACTIVE' : 'INACTIVE';
        echo "- Student {$rel->student_id} ({$rel->student_name}) -> Section {$rel->section_id} ({$rel->section_name}) [{$status}]\n";
    }
    
    // Activate all student-section relationships
    $updated = DB::table('student_section')
        ->update(['is_active' => true]);
    
    echo "\n✓ Updated $updated student-section relationships to active\n";
    
    // Verify the fix
    echo "\n=== VERIFICATION ===\n";
    
    $activeSections = DB::table('student_section')
        ->join('students', 'student_section.student_id', '=', 'students.id')
        ->join('sections', 'student_section.section_id', '=', 'sections.id')
        ->where('student_section.is_active', true)
        ->select('students.id as student_id', 'students.name as student_name', 
                'sections.id as section_id', 'sections.name as section_name')
        ->get();
    
    echo "Active student-section relationships:\n";
    foreach ($activeSections as $rel) {
        echo "- Student {$rel->student_id} ({$rel->student_name}) -> Section {$rel->section_id} ({$rel->section_name})\n";
    }
    
    echo "\n=== STUDENT-SECTION FIX COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
