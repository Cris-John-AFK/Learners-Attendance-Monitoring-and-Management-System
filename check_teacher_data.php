<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING TEACHER DATA FOR MARIA SANTOS (ID: 3) ===\n\n";

// 1. Check if teacher exists
echo "1. Teacher Details:\n";
$teacher = DB::table('teachers')->where('id', 3)->first();
if ($teacher) {
    echo "   ID: {$teacher->id}\n";
    echo "   Name: {$teacher->first_name} {$teacher->last_name}\n";
    echo "   Active: " . ($teacher->is_active ?? 'N/A') . "\n";
} else {
    echo "   ❌ Teacher ID 3 not found!\n";
}

// 2. Check teacher assignments
echo "\n2. Teacher Assignments:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->where('tss.teacher_id', 3)
    ->where('tss.is_active', true)
    ->select('tss.*', 's.name as section_name', 'sub.name as subject_name')
    ->get();

if ($assignments->count() > 0) {
    foreach ($assignments as $assignment) {
        echo "   Section: {$assignment->section_name} (ID: {$assignment->section_id})\n";
        echo "   Subject: {$assignment->subject_name} (ID: {$assignment->subject_id})\n";
        echo "   Role: {$assignment->role}\n";
        echo "   Primary: " . ($assignment->is_primary ? 'Yes' : 'No') . "\n";
        echo "   ---\n";
    }
} else {
    echo "   ❌ No assignments found for teacher ID 3!\n";
}

// 3. Check students in assigned sections
echo "\n3. Students in Assigned Sections:\n";
foreach ($assignments as $assignment) {
    echo "   Section: {$assignment->section_name}\n";
    $students = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->where('ss.section_id', $assignment->section_id)
        ->where('ss.is_active', true)
        ->where('ss.status', 'enrolled')
        ->where('sd.isActive', true)
        ->select('sd.id', 'sd.firstName', 'sd.lastName')
        ->get();
    
    if ($students->count() > 0) {
        foreach ($students as $student) {
            echo "     - {$student->firstName} {$student->lastName} (ID: {$student->id})\n";
        }
    } else {
        echo "     ❌ No active enrolled students found!\n";
    }
    echo "   ---\n";
}

// 4. Check if API endpoints work
echo "\n4. Testing API Endpoint Simulation:\n";
try {
    $teacherData = DB::table('teachers')->where('id', 3)->first();
    if ($teacherData) {
        echo "   ✅ Teacher data query works\n";
    } else {
        echo "   ❌ Teacher data query failed\n";
    }
    
    $assignmentData = DB::table('teacher_section_subject as tss')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
        ->where('tss.teacher_id', 3)
        ->where('tss.is_active', true)
        ->get();
    
    echo "   Assignment count: " . $assignmentData->count() . "\n";
    
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== END CHECK ===\n";
