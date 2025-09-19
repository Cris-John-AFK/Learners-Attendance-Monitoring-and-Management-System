<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Fix All Homeroom Assignments Globally ===\n";

// Get all teachers with their current assignments
$teachersWithAssignments = DB::table('teacher_section_subject as tss')
    ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->select('t.id as teacher_id', 't.first_name', 't.last_name', 's.id as section_id', 's.name as section_name', 's.homeroom_teacher_id')
    ->distinct()
    ->orderBy('t.id')
    ->get();

// Group by teacher
$teacherSections = [];
foreach($teachersWithAssignments as $assignment) {
    $teacherId = $assignment->teacher_id;
    if (!isset($teacherSections[$teacherId])) {
        $teacherSections[$teacherId] = [
            'name' => "{$assignment->first_name} {$assignment->last_name}",
            'sections' => []
        ];
    }
    
    if (!in_array($assignment->section_id, array_column($teacherSections[$teacherId]['sections'], 'id'))) {
        $teacherSections[$teacherId]['sections'][] = [
            'id' => $assignment->section_id,
            'name' => $assignment->section_name,
            'current_homeroom_teacher' => $assignment->homeroom_teacher_id
        ];
    }
}

echo "Current homeroom assignments:\n";
$sectionsWithHomeroom = DB::table('sections')
    ->leftJoin('teachers', 'sections.homeroom_teacher_id', '=', 'teachers.id')
    ->select('sections.id', 'sections.name as section_name', 'sections.homeroom_teacher_id', 'teachers.first_name', 'teachers.last_name')
    ->get();

foreach($sectionsWithHomeroom as $section) {
    $teacherName = $section->first_name ? "{$section->first_name} {$section->last_name}" : "No teacher assigned";
    echo "  Section {$section->id} ({$section->section_name}): Teacher ID {$section->homeroom_teacher_id} - {$teacherName}\n";
}

echo "\n=== Assigning Homeroom Teachers ===\n";

// For each teacher, assign them as homeroom teacher for sections they teach (if not already assigned)
foreach($teacherSections as $teacherId => $teacher) {
    echo "\nProcessing Teacher {$teacherId} ({$teacher['name']}):\n";
    
    foreach($teacher['sections'] as $section) {
        if (!$section['current_homeroom_teacher']) {
            // This section has no homeroom teacher, assign this teacher
            DB::table('sections')
                ->where('id', $section['id'])
                ->update(['homeroom_teacher_id' => $teacherId]);
            
            echo "  ✓ Assigned as homeroom teacher for {$section['name']}\n";
        } else {
            echo "  - {$section['name']} already has homeroom teacher ID {$section['current_homeroom_teacher']}\n";
        }
    }
}

// Also check for teachers who don't have any assignments but should get homeroom assignments
echo "\n=== Checking for teachers without assignments ===\n";
$allTeachers = DB::table('teachers')->select('id', 'first_name', 'last_name')->get();
$teachersWithoutAssignments = [];

foreach($allTeachers as $teacher) {
    if (!isset($teacherSections[$teacher->id])) {
        $teachersWithoutAssignments[] = $teacher;
    }
}

if (!empty($teachersWithoutAssignments)) {
    echo "Teachers without any assignments:\n";
    foreach($teachersWithoutAssignments as $teacher) {
        echo "  - Teacher {$teacher->id}: {$teacher->first_name} {$teacher->last_name}\n";
    }
    
    // Get sections without homeroom teachers
    $sectionsWithoutHomeroom = DB::table('sections')
        ->whereNull('homeroom_teacher_id')
        ->select('id', 'name')
        ->get();
    
    if (!empty($sectionsWithoutHomeroom)) {
        echo "\nSections without homeroom teachers:\n";
        foreach($sectionsWithoutHomeroom as $section) {
            echo "  - Section {$section->id}: {$section->name}\n";
        }
        
        // Assign first available teacher to first available section
        if (count($teachersWithoutAssignments) > 0 && count($sectionsWithoutHomeroom) > 0) {
            $teacher = $teachersWithoutAssignments[0];
            $section = $sectionsWithoutHomeroom[0];
            
            DB::table('sections')
                ->where('id', $section->id)
                ->update(['homeroom_teacher_id' => $teacher->id]);
            
            echo "  ✓ Assigned Teacher {$teacher->id} ({$teacher->first_name} {$teacher->last_name}) as homeroom for {$section->name}\n";
        }
    }
}

echo "\n=== Final Homeroom Assignments ===\n";
$finalAssignments = DB::table('sections')
    ->leftJoin('teachers', 'sections.homeroom_teacher_id', '=', 'teachers.id')
    ->select('sections.id', 'sections.name as section_name', 'sections.homeroom_teacher_id', 'teachers.first_name', 'teachers.last_name')
    ->get();

foreach($finalAssignments as $section) {
    $teacherName = $section->first_name ? "{$section->first_name} {$section->last_name}" : "No teacher assigned";
    echo "  Section {$section->id} ({$section->section_name}): Teacher ID {$section->homeroom_teacher_id} - {$teacherName}\n";
}

echo "\n✅ Homeroom assignment fix completed!\n";

?>
