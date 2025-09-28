<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING STUDENT DATA ===\n";
$activeStudents = \App\Models\Student::where('current_status', 'active')->count();
echo "Active students: " . $activeStudents . "\n";

echo "\n=== CHECKING TEACHER ASSIGNMENTS ===\n";
$teacherAssignments = \DB::table('teacher_section_subject')->where('teacher_id', 3)->where('is_active', true)->count();
echo "Teacher 3 assignments: " . $teacherAssignments . "\n";

echo "\n=== CHECKING STUDENT-SECTION RELATIONSHIPS ===\n";
$sections = \DB::table('student_section')->where('is_active', true)->groupBy('section_id')->selectRaw('section_id, COUNT(*) as student_count')->get();
foreach ($sections as $section) {
    echo "Section " . $section->section_id . ": " . $section->student_count . " students\n";
}

echo "\n=== CHECKING ATTENDANCE RECORDS ===\n";
$recentAttendance = \App\Models\Attendance::where('date', '>=', now()->subDays(30))->count();
echo "Recent attendance records: " . $recentAttendance . "\n";

echo "\n=== CHECKING SPECIFIC TEACHER 3 STUDENTS ===\n";
$teacherStudents = \DB::table('teacher_section_subject as tss')
    ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
    ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
    ->join('sections as s', 'ss.section_id', '=', 's.id')
    ->where('tss.teacher_id', 3)
    ->where('tss.is_active', true)
    ->where('ss.is_active', true)
    ->select('sd.id', 'sd.name', 'ss.section_id', 's.name as section_name')
    ->distinct()
    ->get();

foreach ($teacherStudents as $student) {
    echo "Student ID " . $student->id . ": " . $student->name . " (Section " . $student->section_id . " - " . $student->section_name . ")\n";
}

echo "\n=== CHECKING API ENDPOINTS ===\n";
echo "Testing teacher assignments API...\n";
try {
    $controller = new \App\Http\Controllers\API\AttendanceController();
    // This would need proper request setup
    echo "AttendanceController exists\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>
