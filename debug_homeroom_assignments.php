<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Homeroom Assignments ===\n";

// Check sections table for homeroom teacher assignments
echo "Sections with homeroom teachers:\n";
$sections = DB::table('sections')
    ->leftJoin('teachers', 'sections.homeroom_teacher_id', '=', 'teachers.id')
    ->select('sections.id', 'sections.name as section_name', 'sections.homeroom_teacher_id', 'teachers.first_name', 'teachers.last_name')
    ->get();

foreach($sections as $section) {
    $teacherName = $section->first_name ? "{$section->first_name} {$section->last_name}" : "No teacher assigned";
    echo "  Section {$section->id} ({$section->section_name}): Teacher ID {$section->homeroom_teacher_id} - {$teacherName}\n";
}

// Check teacher assignments
echo "\nTeacher section subject assignments:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->select('t.id as teacher_id', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name', 'tss.role', 'tss.is_primary')
    ->orderBy('t.id')
    ->get();

$teacherGroups = [];
foreach($assignments as $assignment) {
    $teacherId = $assignment->teacher_id;
    if (!isset($teacherGroups[$teacherId])) {
        $teacherGroups[$teacherId] = [
            'name' => "{$assignment->first_name} {$assignment->last_name}",
            'assignments' => []
        ];
    }
    $teacherGroups[$teacherId]['assignments'][] = $assignment;
}

foreach($teacherGroups as $teacherId => $teacher) {
    echo "\nTeacher {$teacherId} ({$teacher['name']}):\n";
    foreach($teacher['assignments'] as $assignment) {
        $subjectName = $assignment->subject_name ?: 'Homeroom';
        echo "  - {$assignment->section_name}: {$subjectName} (Role: {$assignment->role}, Primary: " . ($assignment->is_primary ? 'Yes' : 'No') . ")\n";
    }
}

// Fix homeroom assignments - assign teachers as homeroom teachers for sections they teach
echo "\n=== Fixing Homeroom Assignments ===\n";

foreach($teacherGroups as $teacherId => $teacher) {
    // Get all sections this teacher teaches
    $sectionsTeaching = [];
    foreach($teacher['assignments'] as $assignment) {
        if (!in_array($assignment->section_name, $sectionsTeaching)) {
            $sectionsTeaching[] = $assignment->section_name;
        }
    }
    
    // For each section, check if it needs a homeroom teacher
    foreach($sectionsTeaching as $sectionName) {
        $section = DB::table('sections')->where('name', $sectionName)->first();
        
        if ($section && !$section->homeroom_teacher_id) {
            // Assign this teacher as homeroom teacher
            DB::table('sections')
                ->where('id', $section->id)
                ->update(['homeroom_teacher_id' => $teacherId]);
            
            echo "Assigned Teacher {$teacherId} ({$teacher['name']}) as homeroom teacher for {$sectionName}\n";
        } elseif ($section && $section->homeroom_teacher_id) {
            echo "Section {$sectionName} already has homeroom teacher ID {$section->homeroom_teacher_id}\n";
        }
    }
}

echo "\n=== Updated Sections ===\n";
$updatedSections = DB::table('sections')
    ->leftJoin('teachers', 'sections.homeroom_teacher_id', '=', 'teachers.id')
    ->select('sections.id', 'sections.name as section_name', 'sections.homeroom_teacher_id', 'teachers.first_name', 'teachers.last_name')
    ->get();

foreach($updatedSections as $section) {
    $teacherName = $section->first_name ? "{$section->first_name} {$section->last_name}" : "No teacher assigned";
    echo "  Section {$section->id} ({$section->section_name}): Teacher ID {$section->homeroom_teacher_id} - {$teacherName}\n";
}

?>
