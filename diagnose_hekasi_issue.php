<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== HEKASI SUBJECT ASSIGNMENT DIAGNOSIS ===\n\n";

try {
    // 1. Check if Hekasi subject exists
    echo "1. Checking if 'Hekasi' subject exists:\n";
    $hekasi = DB::table('subjects')->where('name', 'LIKE', '%Hekasi%')->orWhere('name', 'LIKE', '%hekasi%')->get();
    if ($hekasi->count() > 0) {
        foreach ($hekasi as $subject) {
            echo "   ✓ Found: ID={$subject->id}, Name='{$subject->name}'\n";
        }
    } else {
        echo "   ✗ No 'Hekasi' subject found in subjects table\n";
    }
    
    // 2. Check Malikhain section
    echo "\n2. Checking 'Malikhain' section:\n";
    $section = DB::table('sections')->where('name', 'LIKE', '%Malikhain%')->first();
    if ($section) {
        echo "   ✓ Found: ID={$section->id}, Name='{$section->name}'\n";
        echo "   Homeroom Teacher ID: {$section->homeroom_teacher_id}\n";
    } else {
        echo "   ✗ No 'Malikhain' section found\n";
        return;
    }
    
    // 3. Check section-subject relationships
    echo "\n3. Checking subjects assigned to Malikhain section:\n";
    $sectionSubjects = DB::table('section_subject as ss')
        ->join('subjects as s', 'ss.subject_id', '=', 's.id')
        ->where('ss.section_id', $section->id)
        ->select('s.id', 's.name', 'ss.created_at')
        ->get();
    
    if ($sectionSubjects->count() > 0) {
        foreach ($sectionSubjects as $subj) {
            echo "   ✓ Subject: ID={$subj->id}, Name='{$subj->name}', Added: {$subj->created_at}\n";
        }
    } else {
        echo "   ✗ No subjects assigned to Malikhain section\n";
    }
    
    // 4. Check teacher assignments for this section
    echo "\n4. Checking teacher assignments for Malikhain section:\n";
    
    // First check users table structure
    $userColumns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
    $hasName = collect($userColumns)->pluck('column_name')->contains('name');
    $hasFirstName = collect($userColumns)->pluck('column_name')->contains('first_name');
    
    $nameField = $hasName ? 'u.name' : ($hasFirstName ? "CONCAT(u.first_name, ' ', u.last_name)" : 'u.email');
    
    $teacherAssignments = DB::table('teacher_section_subject as tss')
        ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
        ->join('subjects as s', 'tss.subject_id', '=', 's.id')
        ->join('users as u', 't.user_id', '=', 'u.id')
        ->where('tss.section_id', $section->id)
        ->where('tss.is_active', true)
        ->select('t.id as teacher_id', DB::raw("$nameField as teacher_name"), 's.id as subject_id', 's.name as subject_name', 'tss.role', 'tss.is_primary')
        ->get();
    
    if ($teacherAssignments->count() > 0) {
        foreach ($teacherAssignments as $assignment) {
            echo "   ✓ Teacher: {$assignment->teacher_name} (ID={$assignment->teacher_id})\n";
            echo "     Subject: {$assignment->subject_name} (ID={$assignment->subject_id})\n";
            echo "     Role: {$assignment->role}, Primary: " . ($assignment->is_primary ? 'Yes' : 'No') . "\n\n";
        }
    } else {
        echo "   ✗ No teacher assignments found for Malikhain section\n";
    }
    
    // 5. Check if Hekasi is specifically assigned to any teacher in this section
    if ($hekasi->count() > 0) {
        $hekasiId = $hekasi->first()->id;
        echo "5. Checking if Hekasi (ID={$hekasiId}) is assigned to any teacher in Malikhain section:\n";
        
        $hekasiAssignment = DB::table('teacher_section_subject as tss')
            ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('tss.section_id', $section->id)
            ->where('tss.subject_id', $hekasiId)
            ->where('tss.is_active', true)
            ->select('t.id as teacher_id', DB::raw("$nameField as teacher_name"), 'tss.role')
            ->first();
        
        if ($hekasiAssignment) {
            echo "   ✓ Hekasi is assigned to: {$hekasiAssignment->teacher_name} (ID={$hekasiAssignment->teacher_id})\n";
            echo "     Role: {$hekasiAssignment->role}\n";
        } else {
            echo "   ✗ Hekasi is NOT assigned to any teacher in Malikhain section\n";
            echo "   This is likely why it doesn't appear in the teacher's navigation!\n";
        }
    }
    
    // 6. Show all teachers
    echo "\n6. All active teachers in the system:\n";
    $teachers = DB::table('teachers as t')
        ->join('users as u', 't.user_id', '=', 'u.id')
        ->select('t.id', DB::raw("$nameField as teacher_name"), 'u.email')
        ->get();
    
    foreach ($teachers as $teacher) {
        echo "   Teacher: {$teacher->teacher_name} (ID={$teacher->id}, Email: {$teacher->email})\n";
    }
    
    // 7. Suggest fix
    echo "\n=== DIAGNOSIS COMPLETE ===\n";
    echo "LIKELY ISSUE: Adding a subject to a section only creates the section-subject relationship.\n";
    echo "You also need to assign a TEACHER to teach that subject in that section.\n";
    echo "This creates the teacher_section_subject record that makes the subject appear in teacher navigation.\n\n";
    
    if ($hekasi->count() > 0 && isset($section)) {
        echo "SUGGESTED FIX:\n";
        echo "1. Go to Section Management for 'Malikhain'\n";
        echo "2. Find the 'Hekasi' subject\n";
        echo "3. Click 'Assign Teacher' for Hekasi\n";
        echo "4. Select which teacher should teach Hekasi in this section\n";
        echo "5. The subject should then appear in that teacher's navigation\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
