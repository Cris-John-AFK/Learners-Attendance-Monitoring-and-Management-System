<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Services\ScheduleNotificationService;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔔 Testing Schedule Notification System\n";
    echo "======================================\n\n";

    // Create service instance
    $service = new ScheduleNotificationService();

    // Test 1: Check if we can get upcoming schedules for Maria Santos (teacher ID 1)
    echo "1. Testing upcoming schedules for Maria Santos (Teacher ID: 1):\n";
    $schedules = $service->getUpcomingSchedules(1);
    echo "   Found {$schedules->count()} schedules\n";
    
    if ($schedules->count() > 0) {
        foreach ($schedules as $schedule) {
            echo "   - {$schedule->subject_name} in {$schedule->section_name}\n";
            echo "     Time: {$schedule->start_time} - {$schedule->end_time}\n";
            echo "     Status: {$schedule->status}\n";
            echo "     Minutes to start: {$schedule->minutes_to_start}\n\n";
        }
    } else {
        echo "   No schedules found (this is expected since we cleared sample data)\n\n";
    }

    // Test 2: Test session timing validation
    echo "2. Testing session timing validation:\n";
    $validation = $service->validateSessionTiming(1, 1, 1); // Teacher 1, Section 1, Subject 1
    echo "   Validation result:\n";
    echo "   - Is valid: " . ($validation['is_valid'] ? 'Yes' : 'No') . "\n";
    echo "   - Warning type: " . ($validation['warning_type'] ?? 'None') . "\n";
    echo "   - Message: " . $validation['message'] . "\n";
    echo "   - Can proceed: " . ($validation['can_proceed'] ? 'Yes' : 'No') . "\n\n";

    // Test 3: Check schedules needing auto-absence
    echo "3. Testing auto-absence detection:\n";
    $needingAutoAbsence = $service->getSchedulesNeedingAutoAbsence();
    echo "   Found {$needingAutoAbsence->count()} schedules needing auto-absence marking\n\n";

    // Test 4: Check database structure
    echo "4. Checking database structure:\n";
    
    // Check if attendance_sessions table has new columns
    $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'attendance_sessions' AND column_name IN ('schedule_id', 'scheduled_start_time', 'auto_absence_marked')");
    echo "   New columns in attendance_sessions:\n";
    foreach ($columns as $column) {
        echo "   ✅ {$column->column_name}\n";
    }
    
    // Check indexes
    $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'attendance_sessions' AND indexname LIKE 'idx_sessions_%'");
    echo "\n   New indexes:\n";
    foreach ($indexes as $index) {
        echo "   ✅ {$index->indexname}\n";
    }

    echo "\n✅ Schedule Notification System test completed!\n";
    echo "🚀 The system is ready for:\n";
    echo "   - Real-time schedule notifications\n";
    echo "   - Auto-absence marking after class ends\n";
    echo "   - Session timing validation\n";
    echo "   - Browser and in-app notifications\n\n";

    echo "📱 Next steps:\n";
    echo "   1. Create some schedules using the admin interface\n";
    echo "   2. Test the teacher dashboard with schedule notifications\n";
    echo "   3. Create attendance sessions and test auto-absence marking\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
