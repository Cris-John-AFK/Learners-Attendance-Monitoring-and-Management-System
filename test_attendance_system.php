<?php

require_once __DIR__ . '/lamms-backend/vendor/autoload.php';

use App\Models\AttendanceStatus;
use App\Models\Student;
use App\Models\Section;
use App\Models\Subject;

// Simple test to check if the attendance system is working
echo "Testing Attendance System\n";
echo "========================\n\n";

try {
    // Test 1: Check if attendance statuses were created
    echo "1. Testing Attendance Statuses:\n";
    $statuses = AttendanceStatus::all();
    echo "   Found " . $statuses->count() . " attendance statuses:\n";
    foreach ($statuses as $status) {
        echo "   - {$status->code}: {$status->name}\n";
    }
    echo "\n";

    // Test 2: Check students
    echo "2. Testing Students:\n";
    $students = Student::take(5)->get();
    echo "   Found " . $students->count() . " students (showing first 5):\n";
    foreach ($students as $student) {
        echo "   - ID: {$student->id}, Name: " . ($student->name ?? $student->firstName . ' ' . $student->lastName) . "\n";
    }
    echo "\n";

    // Test 3: Check sections
    echo "3. Testing Sections:\n";
    $sections = Section::take(3)->get();
    echo "   Found " . $sections->count() . " sections (showing first 3):\n";
    foreach ($sections as $section) {
        echo "   - ID: {$section->id}, Name: {$section->name}\n";
    }
    echo "\n";

    // Test 4: Check subjects
    echo "4. Testing Subjects:\n";
    $subjects = Subject::take(3)->get();
    echo "   Found " . $subjects->count() . " subjects (showing first 3):\n";
    foreach ($subjects as $subject) {
        echo "   - ID: {$subject->id}, Name: {$subject->name}\n";
    }
    echo "\n";

    echo "✅ Attendance system setup appears to be working!\n";
    echo "\nNext steps:\n";
    echo "- Use the API endpoints to mark attendance\n";
    echo "- Create frontend interface to interact with attendance\n";
    echo "- Test with actual student data\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

?>