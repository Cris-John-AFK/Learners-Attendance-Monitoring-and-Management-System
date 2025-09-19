<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG: Student Section Assignments ===\n";

// Check section 1 (Kinder One)
$section = Section::find(1);
if ($section) {
    echo "Section found: {$section->name}\n";
    
    // Check student_section pivot table
    $pivotRecords = DB::table('student_section')
        ->where('section_id', 1)
        ->get();
    
    echo "Student-Section pivot records: " . $pivotRecords->count() . "\n";
    foreach ($pivotRecords as $record) {
        echo "  Student ID: {$record->student_id}, Active: " . ($record->is_active ? 'Yes' : 'No') . "\n";
    }
    
    // Check active students through relationship
    $activeStudents = $section->activeStudents()->get();
    echo "Active students count: " . $activeStudents->count() . "\n";
    
    foreach ($activeStudents as $student) {
        echo "  Student: {$student->firstName} {$student->lastName} (ID: {$student->id}, Status: {$student->status})\n";
    }
    
    // Check all students in student_details table
    echo "\n=== All Students in Database ===\n";
    $allStudents = Student::all();
    echo "Total students in database: " . $allStudents->count() . "\n";
    
    foreach ($allStudents as $student) {
        echo "  Student: {$student->firstName} {$student->lastName} (ID: {$student->id}, Status: {$student->status})\n";
    }
    
} else {
    echo "Section 1 not found\n";
}

?>
