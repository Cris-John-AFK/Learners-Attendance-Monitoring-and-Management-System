<?php

require_once 'lamms-backend/vendor/autoload.php';
require_once 'lamms-backend/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "=== CHECKING STUDENT-SECTION RELATIONSHIPS ===\n\n";

try {
    // Check all student-section relationships
    $relationships = DB::table('student_section')
        ->join('students', 'student_section.student_id', '=', 'students.id')
        ->join('sections', 'student_section.section_id', '=', 'sections.id')
        ->select('students.id as student_id', 'students.name as student_name', 
                'sections.id as section_id', 'sections.name as section_name', 
                'student_section.is_active')
        ->get();
    
    echo "All student-section relationships:\n";
    foreach ($relationships as $rel) {
        $status = $rel->is_active ? 'ACTIVE' : 'INACTIVE';
        echo "- Student {$rel->student_id} ({$rel->student_name}) -> Section {$rel->section_id} ({$rel->section_name}) [{$status}]\n";
    }
    
    echo "\n=== TESTING SPECIFIC QUERIES ===\n";
    
    // Test the exact query used in AttendanceController
    echo "\nTesting student 3 in section 13:\n";
    $student3InSection13 = DB::table('students')
        ->whereHas('sections', function($query) {
            $query->where('sections.id', 13)
                  ->where('student_section.is_active', true);
        })
        ->where('id', 3)
        ->exists();
    
    echo "Query result: " . ($student3InSection13 ? 'FOUND' : 'NOT FOUND') . "\n";
    
    // Alternative query approach
    echo "\nAlternative query approach:\n";
    $altQuery = DB::table('students')
        ->join('student_section', 'students.id', '=', 'student_section.student_id')
        ->where('students.id', 3)
        ->where('student_section.section_id', 13)
        ->where('student_section.is_active', true)
        ->exists();
    
    echo "Alternative query result: " . ($altQuery ? 'FOUND' : 'NOT FOUND') . "\n";
    
    // Check if is_active column has correct values
    echo "\nChecking is_active values:\n";
    $activeCheck = DB::table('student_section')
        ->select('student_id', 'section_id', 'is_active')
        ->get();
    
    foreach ($activeCheck as $check) {
        $activeStatus = $check->is_active ? 'true' : 'false';
        echo "- Student {$check->student_id} -> Section {$check->section_id}: is_active = {$activeStatus}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
