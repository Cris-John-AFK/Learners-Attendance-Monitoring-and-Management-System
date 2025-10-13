<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TESTING SECTION AND GRADE DATA ===\n";

$sectionId = 226;

// Test the exact query from AttendanceSessionController
$students = DB::table('student_details as sd')
    ->join('student_section as ss', function($join) use ($sectionId) {
        $join->on('sd.id', '=', 'ss.student_id')
             ->where('ss.section_id', '=', $sectionId)
             ->where('ss.is_active', '=', 1);
    })
    ->join('sections as s', 'ss.section_id', '=', 's.id')
    ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
    ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
    ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
    ->select([
        'sd.id',
        'sd.firstName as first_name',
        'sd.lastName as last_name',
        'sd.enrollment_status',
        's.name as section_name',
        'g.name as grade_name'
    ])
    ->limit(3)
    ->get();

echo "Found " . $students->count() . " students with section/grade data:\n";
foreach ($students as $student) {
    echo "- {$student->first_name} {$student->last_name} (ID: {$student->id})\n";
    echo "  Section: '" . ($student->section_name ?? 'NULL') . "'\n";
    echo "  Grade: '" . ($student->grade_name ?? 'NULL') . "'\n";
    echo "  Status: '" . ($student->enrollment_status ?? 'NULL') . "'\n";
    echo "---\n";
}

echo "\n=== CHECKING SECTION 226 DETAILS ===\n";
$sectionInfo = DB::table('sections as s')
    ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
    ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
    ->where('s.id', 226)
    ->select('s.name as section_name', 'g.name as grade_name', 's.curriculum_grade_id', 'cg.grade_id')
    ->first();

if ($sectionInfo) {
    echo "Section 226 Info:\n";
    echo "- Section Name: '" . ($sectionInfo->section_name ?? 'NULL') . "'\n";
    echo "- Grade Name: '" . ($sectionInfo->grade_name ?? 'NULL') . "'\n";
    echo "- Curriculum Grade ID: '" . ($sectionInfo->curriculum_grade_id ?? 'NULL') . "'\n";
    echo "- Grade ID: '" . ($sectionInfo->grade_id ?? 'NULL') . "'\n";
} else {
    echo "Section 226 not found!\n";
}
