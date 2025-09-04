<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel app
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

echo "=== SETTING UP GRADE 3 DEMO ===\n";

// 1. Update all students to Grade 3
$students = Student::all();
foreach($students as $student) {
    $student->update(['gradeLevel' => '3']);
}
echo "✓ Updated {$students->count()} students to Grade 3\n";

// 2. Get Grade 3 section (Malikhain - section 13)
$section = Section::find(13);
if ($section) {
    echo "✓ Using section: {$section->name} (ID: {$section->id})\n";
    
    // 3. Assign all students to this section
    DB::table('student_section')->delete(); // Clear existing
    foreach($students as $student) {
        DB::table('student_section')->insert([
            'student_id' => $student->id,
            'section_id' => 13,
            'school_year' => '2025-2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    echo "✓ Assigned all students to section {$section->name}\n";
}

// 4. Verify setup
$sectionStudents = DB::table('student_section')
    ->join('student_details', 'student_section.student_id', '=', 'student_details.id')
    ->where('student_section.section_id', 13)
    ->where('student_section.is_active', true)
    ->select('student_details.name', 'student_details.gradeLevel')
    ->get();

echo "\n=== GRADE 3 DEMO SETUP COMPLETE ===\n";
echo "Section: Malikhain (Grade 3)\n";
echo "Students: {$sectionStudents->count()}\n";
foreach($sectionStudents as $student) {
    echo "- {$student->name} (Grade {$student->gradeLevel})\n";
}
echo "=== READY FOR DEMO ===\n";
?>
