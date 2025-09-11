<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FIXING EXISTING HEKASI ASSIGNMENT ===\n\n";

try {
    // Find Malikhain section and Hekasi subject
    $section = DB::table('sections')->where('name', 'Malikhain')->first();
    $hekasi = DB::table('subjects')->where('name', 'Hekasi')->first();
    
    if (!$section || !$hekasi) {
        echo "Error: Could not find Malikhain section or Hekasi subject\n";
        exit(1);
    }
    
    echo "Found:\n";
    echo "- Section: {$section->name} (ID={$section->id})\n";
    echo "- Subject: {$hekasi->name} (ID={$hekasi->id})\n";
    echo "- Homeroom Teacher ID: {$section->homeroom_teacher_id}\n\n";
    
    if (!$section->homeroom_teacher_id) {
        echo "Error: No homeroom teacher assigned to Malikhain section\n";
        exit(1);
    }
    
    // Check if assignment already exists
    $existingAssignment = DB::table('teacher_section_subject')
        ->where('teacher_id', $section->homeroom_teacher_id)
        ->where('section_id', $section->id)
        ->where('subject_id', $hekasi->id)
        ->first();
    
    if ($existingAssignment) {
        echo "Assignment already exists:\n";
        echo "- Teacher ID: {$existingAssignment->teacher_id}\n";
        echo "- Section ID: {$existingAssignment->section_id}\n";
        echo "- Subject ID: {$existingAssignment->subject_id}\n";
        echo "- Role: {$existingAssignment->role}\n";
        echo "- Is Active: " . ($existingAssignment->is_active ? 'Yes' : 'No') . "\n";
        
        if (!$existingAssignment->is_active) {
            echo "\nActivating existing assignment...\n";
            DB::table('teacher_section_subject')
                ->where('id', $existingAssignment->id)
                ->update(['is_active' => true]);
            echo "✓ Assignment activated\n";
        } else {
            echo "\n✓ Assignment is already active\n";
        }
    } else {
        echo "Creating new teacher-subject assignment...\n";
        
        $assignmentId = DB::table('teacher_section_subject')->insertGetId([
            'teacher_id' => $section->homeroom_teacher_id,
            'section_id' => $section->id,
            'subject_id' => $hekasi->id,
            'role' => 'teacher',
            'is_primary' => true,
            'is_active' => true
        ]);
        
        echo "✓ Created assignment with ID: {$assignmentId}\n";
    }
    
    // Verify the assignment
    echo "\nVerifying assignment...\n";
    $finalAssignment = DB::table('teacher_section_subject as tss')
        ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
        ->join('users as u', 't.user_id', '=', 'u.id')
        ->where('tss.section_id', $section->id)
        ->where('tss.subject_id', $hekasi->id)
        ->where('tss.is_active', true)
        ->select('t.id as teacher_id', 'u.email as teacher_email', 'tss.role', 'tss.is_primary')
        ->first();
    
    if ($finalAssignment) {
        echo "✓ Assignment verified:\n";
        echo "  Teacher ID: {$finalAssignment->teacher_id}\n";
        echo "  Teacher Email: {$finalAssignment->teacher_email}\n";
        echo "  Role: {$finalAssignment->role}\n";
        echo "  Is Primary: " . ($finalAssignment->is_primary ? 'Yes' : 'No') . "\n";
        echo "\n=== SUCCESS ===\n";
        echo "Hekasi should now appear in the teacher's navigation!\n";
        echo "Please refresh the teacher page to see the changes.\n";
    } else {
        echo "✗ Assignment verification failed\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
