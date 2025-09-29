<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking Maria Santos' Real Assignments\n";
    echo "==========================================\n\n";

    // Find Maria Santos
    $maria = DB::table('teachers')
        ->where('first_name', 'Maria')
        ->where('last_name', 'Santos')
        ->first();

    if (!$maria) {
        echo "❌ Maria Santos not found in teachers table\n";
        exit(1);
    }

    echo "✅ Found Maria Santos (ID: {$maria->id})\n";
    echo "   Username: {$maria->username}\n\n";

    // Check her section assignments (homeroom teacher)
    echo "📚 Homeroom Teacher Assignments:\n";
    $homeroomSections = DB::table('sections')
        ->where('homeroom_teacher_id', $maria->id)
        ->get();

    if ($homeroomSections->count() > 0) {
        foreach ($homeroomSections as $section) {
            echo "   - Section: {$section->name} (ID: {$section->id})\n";
        }
    } else {
        echo "   - No homeroom assignments found\n";
    }
    echo "\n";

    // Check curriculum assignments (subject teacher)
    echo "📖 Subject Teacher Assignments:\n";
    $subjectAssignments = DB::table('curriculums as c')
        ->join('sections as sec', 'c.section_id', '=', 'sec.id')
        ->join('subjects as sub', 'c.subject_id', '=', 'sub.id')
        ->where('c.teacher_id', $maria->id)
        ->select([
            'c.id as curriculum_id',
            'sec.name as section_name',
            'sec.id as section_id',
            'sub.name as subject_name',
            'sub.id as subject_id'
        ])
        ->get();

    if ($subjectAssignments->count() > 0) {
        foreach ($subjectAssignments as $assignment) {
            echo "   - {$assignment->subject_name} in {$assignment->section_name} (Section ID: {$assignment->section_id}, Subject ID: {$assignment->subject_id})\n";
        }
    } else {
        echo "   - No subject assignments found\n";
    }
    echo "\n";

    // Check current schedules
    echo "🗓️ Current Schedules in Database:\n";
    $schedules = DB::table('subject_schedules as ss')
        ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
        ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
        ->where('ss.teacher_id', $maria->id)
        ->select([
            'ss.day',
            'ss.start_time',
            'ss.end_time',
            'sec.name as section_name',
            'sec.id as section_id',
            'sub.name as subject_name',
            'sub.id as subject_id'
        ])
        ->get();

    if ($schedules->count() > 0) {
        foreach ($schedules as $schedule) {
            echo "   - {$schedule->day} {$schedule->start_time}-{$schedule->end_time}: {$schedule->subject_name} in {$schedule->section_name} (Section ID: {$schedule->section_id})\n";
        }
    } else {
        echo "   - No schedules found\n";
    }
    echo "\n";

    // Check if schedules match assignments
    echo "🔍 Validation:\n";
    $validSchedules = 0;
    $invalidSchedules = 0;

    foreach ($schedules as $schedule) {
        $matchingAssignment = $subjectAssignments->first(function ($assignment) use ($schedule) {
            return $assignment->section_id == $schedule->section_id && 
                   $assignment->subject_id == $schedule->subject_id;
        });

        if ($matchingAssignment) {
            echo "   ✅ Valid: {$schedule->subject_name} in {$schedule->section_name}\n";
            $validSchedules++;
        } else {
            echo "   ❌ Invalid: {$schedule->subject_name} in {$schedule->section_name} - NO MATCHING ASSIGNMENT\n";
            $invalidSchedules++;
        }
    }

    echo "\n📊 Summary:\n";
    echo "   - Valid schedules: {$validSchedules}\n";
    echo "   - Invalid schedules: {$invalidSchedules}\n";
    echo "   - Total assignments: " . $subjectAssignments->count() . "\n";
    echo "   - Total schedules: " . $schedules->count() . "\n";

    if ($invalidSchedules > 0) {
        echo "\n⚠️  There are schedules that don't match teacher assignments!\n";
        echo "   This explains why the sidebar and schedules don't match.\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
