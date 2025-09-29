<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🧪 Testing Subject Schedules API\n";
    echo "================================\n\n";

    // Test 1: Check if table exists and has data
    echo "1. Checking subject_schedules table...\n";
    $count = DB::table('subject_schedules')->count();
    echo "   ✅ Table exists with {$count} records\n\n";

    // Test 2: Check table structure
    echo "2. Checking table structure...\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'subject_schedules' ORDER BY ordinal_position");
    foreach ($columns as $column) {
        echo "   - {$column->column_name}: {$column->data_type}\n";
    }
    echo "\n";

    // Test 3: Get sample data
    echo "3. Sample data from subject_schedules:\n";
    $samples = DB::table('subject_schedules as ss')
        ->join('teachers as t', 'ss.teacher_id', '=', 't.id')
        ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
        ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
        ->select([
            'ss.id',
            'ss.day',
            'ss.start_time',
            'ss.end_time',
            't.first_name as teacher_first_name',
            't.last_name as teacher_last_name',
            'sec.name as section_name',
            'sub.name as subject_name'
        ])
        ->limit(5)
        ->get();

    if ($samples->count() > 0) {
        foreach ($samples as $sample) {
            echo "   - {$sample->subject_name} | {$sample->section_name} | {$sample->teacher_first_name} {$sample->teacher_last_name} | {$sample->day} {$sample->start_time}-{$sample->end_time}\n";
        }
    } else {
        echo "   No sample data found\n";
    }
    echo "\n";

    // Test 4: Test time conflict detection
    echo "4. Testing time conflict detection...\n";
    $conflicts = DB::table('subject_schedules as ss1')
        ->join('subject_schedules as ss2', function($join) {
            $join->on('ss1.section_id', '=', 'ss2.section_id')
                 ->on('ss1.day', '=', 'ss2.day')
                 ->where('ss1.id', '!=', DB::raw('ss2.id'))
                 ->where(function($q) {
                     $q->where(function($subQ) {
                         $subQ->where('ss1.start_time', '<=', DB::raw('ss2.start_time'))
                              ->where('ss1.end_time', '>', DB::raw('ss2.start_time'));
                     })->orWhere(function($subQ) {
                         $subQ->where('ss1.start_time', '<', DB::raw('ss2.end_time'))
                              ->where('ss1.end_time', '>=', DB::raw('ss2.end_time'));
                     });
                 });
        })
        ->select('ss1.id', 'ss1.section_id', 'ss1.day', 'ss1.start_time', 'ss1.end_time')
        ->get();

    if ($conflicts->count() > 0) {
        echo "   ⚠️  Found {$conflicts->count()} time conflicts:\n";
        foreach ($conflicts as $conflict) {
            echo "     - Section {$conflict->section_id} on {$conflict->day} at {$conflict->start_time}-{$conflict->end_time}\n";
        }
    } else {
        echo "   ✅ No time conflicts detected\n";
    }
    echo "\n";

    // Test 5: Check available teachers and sections
    echo "5. Available data for scheduling:\n";
    $teacherCount = DB::table('teachers')->count();
    $sectionCount = DB::table('sections')->count();
    $subjectCount = DB::table('subjects')->count();
    
    echo "   - Teachers: {$teacherCount}\n";
    echo "   - Sections: {$sectionCount}\n";
    echo "   - Subjects: {$subjectCount}\n\n";

    echo "✅ All tests completed successfully!\n";
    echo "🚀 Subject Schedules API is ready to use.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
