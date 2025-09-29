<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🗓️ Creating Sample Subject Schedules\n";
    echo "====================================\n\n";

    // Clear existing schedules
    DB::table('subject_schedules')->delete();
    echo "✅ Cleared existing schedules\n";

    // Get available data
    $teachers = DB::table('teachers')->limit(3)->get();
    $sections = DB::table('sections')->limit(2)->get();
    $subjects = DB::table('subjects')->limit(4)->get();

    echo "📊 Available data:\n";
    echo "   - Teachers: " . $teachers->count() . "\n";
    echo "   - Sections: " . $sections->count() . "\n";
    echo "   - Subjects: " . $subjects->count() . "\n\n";

    if ($teachers->count() == 0 || $sections->count() == 0 || $subjects->count() == 0) {
        echo "❌ Not enough data to create schedules. Need teachers, sections, and subjects.\n";
        exit(1);
    }

    // Create sample schedules
    $schedules = [
        // Teacher 1 - Section 1
        [
            'teacher_id' => $teachers[0]->id,
            'section_id' => $sections[0]->id,
            'subject_id' => $subjects[0]->id,
            'day' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00'
        ],
        [
            'teacher_id' => $teachers[0]->id,
            'section_id' => $sections[0]->id,
            'subject_id' => $subjects[1]->id,
            'day' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00'
        ],
        [
            'teacher_id' => $teachers[0]->id,
            'section_id' => $sections[0]->id,
            'subject_id' => $subjects[0]->id,
            'day' => 'Tuesday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00'
        ],
        
        // Teacher 2 - Section 2
        [
            'teacher_id' => $teachers[1]->id,
            'section_id' => $sections[1]->id,
            'subject_id' => $subjects[2]->id,
            'day' => 'Monday',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00'
        ],
        [
            'teacher_id' => $teachers[1]->id,
            'section_id' => $sections[1]->id,
            'subject_id' => $subjects[3]->id,
            'day' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00'
        ],
    ];

    $created = 0;
    foreach ($schedules as $schedule) {
        $schedule['is_active'] = true;
        $schedule['created_at'] = now();
        $schedule['updated_at'] = now();
        
        DB::table('subject_schedules')->insert($schedule);
        $created++;
        
        echo "✅ Created schedule: {$schedule['day']} {$schedule['start_time']}-{$schedule['end_time']}\n";
    }

    echo "\n🎉 Successfully created {$created} sample schedules!\n\n";

    // Display created schedules
    echo "📋 Created Schedules:\n";
    $createdSchedules = DB::table('subject_schedules as ss')
        ->join('teachers as t', 'ss.teacher_id', '=', 't.id')
        ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
        ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
        ->select([
            'ss.day',
            'ss.start_time',
            'ss.end_time',
            't.first_name',
            't.last_name',
            'sec.name as section_name',
            'sub.name as subject_name'
        ])
        ->orderBy('ss.day')
        ->orderBy('ss.start_time')
        ->get();

    foreach ($createdSchedules as $schedule) {
        echo "   - {$schedule->day} {$schedule->start_time}-{$schedule->end_time} | {$schedule->subject_name} | {$schedule->section_name} | {$schedule->first_name} {$schedule->last_name}\n";
    }

    echo "\n🚀 Sample schedules are ready for testing!\n";
    echo "📱 You can now test the scheduling interfaces:\n";
    echo "   - Admin: /admin/subject-scheduling\n";
    echo "   - Teacher: /teacher/schedules\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
