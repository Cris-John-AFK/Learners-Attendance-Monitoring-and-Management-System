<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Fixing homeroom teacher roles...\n\n";

// Get all sections with homeroom teachers
$sectionsWithHomeroom = DB::table('sections')
    ->whereNotNull('homeroom_teacher_id')
    ->select('id', 'name', 'homeroom_teacher_id')
    ->get();

echo "Found " . $sectionsWithHomeroom->count() . " sections with homeroom teachers\n\n";

$updated = 0;
$created = 0;

foreach ($sectionsWithHomeroom as $section) {
    $teacherId = $section->homeroom_teacher_id;
    $sectionId = $section->id;
    
    // Get teacher name
    $teacher = DB::table('teachers')->where('id', $teacherId)->first();
    $teacherName = $teacher ? "{$teacher->first_name} {$teacher->last_name}" : "Teacher $teacherId";
    
    echo "Section: {$section->name} => Homeroom Teacher: $teacherName (ID: $teacherId)\n";
    
    // Check if this teacher-section assignment exists in teacher_section_subject
    $existingAssignment = DB::table('teacher_section_subject')
        ->where('teacher_id', $teacherId)
        ->where('section_id', $sectionId)
        ->first();
    
    if ($existingAssignment) {
        // Update existing assignment to homeroom role
        DB::table('teacher_section_subject')
            ->where('teacher_id', $teacherId)
            ->where('section_id', $sectionId)
            ->update(['role' => 'homeroom']);
        
        echo "  ✅ Updated existing assignment (ID: {$existingAssignment->id}) to role='homeroom'\n";
        $updated++;
    } else {
        // Create new homeroom assignment
        DB::table('teacher_section_subject')->insert([
            'teacher_id' => $teacherId,
            'section_id' => $sectionId,
            'subject_id' => null, // Homeroom doesn't need a specific subject
            'role' => 'homeroom',
            'is_primary' => true,
            'is_active' => true
        ]);
        
        echo "  ✅ Created new homeroom assignment\n";
        $created++;
    }
    
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Updated existing assignments: $updated\n";
echo "Created new assignments: $created\n";

// Show all homeroom assignments
echo "\n=== ALL HOMEROOM ASSIGNMENTS ===\n";
$homeroomAssignments = DB::table('teacher_section_subject as tss')
    ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->where('tss.role', 'homeroom')
    ->select('t.first_name', 't.last_name', 's.name as section_name', 'tss.is_active')
    ->get();

foreach ($homeroomAssignments as $assignment) {
    $status = $assignment->is_active ? 'Active' : 'Inactive';
    echo "{$assignment->first_name} {$assignment->last_name} => {$assignment->section_name} ($status)\n";
}

echo "\nDone!\n";
