<?php
require_once 'lamms-backend/vendor/autoload.php';
$app = require_once 'lamms-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\Section;

echo "Adding a new Grade 3 student for testing...\n";

// Create a new student
$student = new Student();
$student->firstName = 'Test';
$student->lastName = 'Student New';
$student->gradeLevel = 3;
$student->email = 'teststudent@example.com';
$student->lrn = 'LRN' . time();
$student->save();

echo "Created student: " . $student->name . " (ID: " . $student->id . ")\n";

// Assign to Malikhain section (ID: 13)
$malikhainSection = Section::find(13);
if ($malikhainSection) {
    $student->sections()->attach($malikhainSection->id, [
        'school_year' => '2025-2026',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "Assigned student to section: " . $malikhainSection->name . "\n";
} else {
    echo "Warning: Malikhain section not found\n";
}

echo "New student enrollment test completed!\n";
echo "Student should now appear in Grade 3 API and unassigned students panel.\n";
