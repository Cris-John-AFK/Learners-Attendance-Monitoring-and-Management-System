<?php
require_once 'lamms-backend/vendor/autoload.php';
$app = require_once 'lamms-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;

echo "Current Grade 3 students:\n";
$students = Student::where('gradeLevel', 3)->with('currentSection')->get();
foreach ($students as $student) {
    $currentSection = $student->currentSection->first();
    echo "- " . $student->name . " (ID: " . $student->id . ", Section: " . ($currentSection ? $currentSection->name : 'None') . ")\n";
}
echo "Total: " . $students->count() . " students\n";
