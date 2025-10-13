<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TESTING STUDENT FILTERING FOR SECTION 226 ===\n";

$sectionId = 226;

// Simulate the exact query from AttendanceSessionController
$students = DB::table('student_details as sd')
    ->join('student_section as ss', function($join) use ($sectionId) {
        $join->on('sd.id', '=', 'ss.student_id')
             ->where('ss.section_id', '=', $sectionId)
             ->where('ss.is_active', '=', 1);
    })
    ->join('sections as s', 'ss.section_id', '=', 's.id')
    ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
    ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
    // CRITICAL: Only include active students, explicitly exclude dropped out students
    ->where(function($query) {
        $query->where(function($subQuery) {
            $subQuery->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                     ->orWhereNull('sd.enrollment_status');
        })
        ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased']);
    })
    ->select([
        'sd.id',
        'sd.firstName as first_name',
        'sd.lastName as last_name',
        'sd.enrollment_status'
    ])
    ->get();

echo "Found " . $students->count() . " students after filtering:\n";
foreach ($students as $student) {
    echo "- {$student->first_name} {$student->last_name} (ID: {$student->id}) - Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
}

echo "\n=== STUDENTS THAT SHOULD BE EXCLUDED ===\n";
$excludedStudents = DB::table('student_details as sd')
    ->join('student_section as ss', function($join) use ($sectionId) {
        $join->on('sd.id', '=', 'ss.student_id')
             ->where('ss.section_id', '=', $sectionId)
             ->where('ss.is_active', '=', 1);
    })
    ->whereIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
    ->get();

echo "Found " . $excludedStudents->count() . " students that should be excluded:\n";
foreach ($excludedStudents as $student) {
    echo "- {$student->firstName} {$student->lastName} (ID: {$student->id}) - Status: '{$student->enrollment_status}'\n";
}
