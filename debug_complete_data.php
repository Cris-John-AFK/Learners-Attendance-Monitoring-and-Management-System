<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== COMPLETE DATA ANALYSIS FOR TEACHER 1 ===\n";

// 1. Check teacher assignments
echo "\n1. TEACHER ASSIGNMENTS:\n";
$assignments = \DB::table('teacher_section_subject as tss')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->join('grades as g', 's.curriculum_grade_id', '=', 'g.id')
    ->where('tss.teacher_id', 1)
    ->where('tss.is_active', true)
    ->select('tss.*', 's.name as section_name', 'sub.name as subject_name', 'g.name as grade_name')
    ->get();

foreach ($assignments as $assignment) {
    echo "  - Section: {$assignment->section_name} (ID: {$assignment->section_id})\n";
    echo "    Subject: {$assignment->subject_name} (ID: {$assignment->subject_id})\n";
    echo "    Grade: {$assignment->grade_name}\n";
    echo "    Role: {$assignment->role}\n\n";
}

// 2. Check students in assigned sections
echo "\n2. STUDENTS IN ASSIGNED SECTIONS:\n";
$students = \DB::table('student_details as sd')
    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
    ->join('teacher_section_subject as tss', 'ss.section_id', '=', 'tss.section_id')
    ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
    ->join('grades as g', 'sec.curriculum_grade_id', '=', 'g.id')
    ->where('tss.teacher_id', 1)
    ->where('tss.is_active', true)
    ->where('ss.is_active', true)
    ->where('sd.current_status', 'active')
    ->select('sd.id', 'sd.name', 'sd.firstName', 'sd.lastName', 'ss.section_id', 'sec.name as section_name', 'g.name as grade_name', 'tss.subject_id')
    ->distinct()
    ->get();

foreach ($students as $student) {
    echo "  - ID: {$student->id}, Name: {$student->name}\n";
    echo "    Section: {$student->section_name} (ID: {$student->section_id})\n";
    echo "    Grade: {$student->grade_name}\n";
    echo "    Subject: {$student->subject_id}\n\n";
}

// 3. Check attendance records
echo "\n3. ATTENDANCE RECORDS (Last 30 days):\n";
$attendanceRecords = \DB::table('attendances as att')
    ->join('student_details as sd', 'att.student_id', '=', 'sd.id')
    ->where('att.date', '>=', now()->subDays(30))
    ->where('att.teacher_id', 1)
    ->select('att.*', 'sd.name as student_name')
    ->orderBy('att.date', 'desc')
    ->get();

echo "Total records: " . $attendanceRecords->count() . "\n";
foreach ($attendanceRecords->take(10) as $record) {
    echo "  - {$record->student_name}: {$record->status} on {$record->date}\n";
}

// 4. Check attendance statuses
echo "\n4. ATTENDANCE STATUSES:\n";
$statuses = \DB::table('attendance_statuses')->get();
foreach ($statuses as $status) {
    echo "  - ID: {$status->id}, Code: {$status->code}, Name: {$status->name}\n";
}

// 5. Check grades table
echo "\n5. GRADES:\n";
$grades = \DB::table('grades')->get();
foreach ($grades as $grade) {
    echo "  - ID: {$grade->id}, Name: {$grade->name}\n";
}

// 6. Check sections with proper grade relationships
echo "\n6. SECTIONS WITH GRADES:\n";
$sections = \DB::table('sections as s')
    ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
    ->join('grades as g', 'cg.grade_id', '=', 'g.id')
    ->select('s.id', 's.name as section_name', 'g.name as grade_name', 's.homeroom_teacher_id')
    ->get();

foreach ($sections as $section) {
    echo "  - Section: {$section->section_name} (ID: {$section->id})\n";
    echo "    Grade: {$section->grade_name}\n";
    echo "    Homeroom Teacher: {$section->homeroom_teacher_id}\n\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";

?>
