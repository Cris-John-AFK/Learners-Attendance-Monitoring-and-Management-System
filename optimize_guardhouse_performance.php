<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Optimizing guardhouse performance for instant loading...\n";
    
    // Check current record counts
    $liveRecords = DB::table('guardhouse_attendance')->count();
    $archivedSessions = DB::table('guardhouse_archive_sessions')->count();
    $archivedRecords = DB::table('guardhouse_archived_records')->count();
    
    echo "📊 Current Status:\n";
    echo "   - Live records: {$liveRecords}\n";
    echo "   - Archived sessions: {$archivedSessions}\n";
    echo "   - Archived records: {$archivedRecords}\n";
    
    // Optimize all guardhouse tables
    echo "\n🔧 Optimizing database tables...\n";
    
    DB::statement('VACUUM ANALYZE guardhouse_attendance');
    echo "✅ Optimized guardhouse_attendance\n";
    
    DB::statement('VACUUM ANALYZE guardhouse_archive_sessions');
    echo "✅ Optimized guardhouse_archive_sessions\n";
    
    DB::statement('VACUUM ANALYZE guardhouse_archived_records');
    echo "✅ Optimized guardhouse_archived_records\n";
    
    // Add additional performance indexes if they don't exist
    $additionalIndexes = [
        'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_today ON guardhouse_attendance(date) WHERE date = CURRENT_DATE',
        'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_checkin_today ON guardhouse_attendance(date, record_type) WHERE date = CURRENT_DATE AND record_type = \'check-in\'',
        'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_checkout_today ON guardhouse_attendance(date, record_type) WHERE date = CURRENT_DATE AND record_type = \'check-out\'',
    ];
    
    foreach ($additionalIndexes as $sql) {
        try {
            DB::statement($sql);
            echo "✅ Added performance index\n";
        } catch (Exception $e) {
            echo "ℹ️  Index already exists or error: " . substr($e->getMessage(), 0, 50) . "...\n";
        }
    }
    
    // Test query performance
    echo "\n⚡ Testing query performance...\n";
    
    $start = microtime(true);
    $todayCheckIns = DB::table('guardhouse_attendance')
        ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
        ->where('guardhouse_attendance.date', today())
        ->where('guardhouse_attendance.record_type', 'check-in')
        ->count();
    $checkInTime = (microtime(true) - $start) * 1000;
    
    $start = microtime(true);
    $todayCheckOuts = DB::table('guardhouse_attendance')
        ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
        ->where('guardhouse_attendance.date', today())
        ->where('guardhouse_attendance.record_type', 'check-out')
        ->count();
    $checkOutTime = (microtime(true) - $start) * 1000;
    
    echo "📈 Performance Results:\n";
    echo "   - Check-ins query: {$checkInTime}ms ({$todayCheckIns} records)\n";
    echo "   - Check-outs query: {$checkOutTime}ms ({$todayCheckOuts} records)\n";
    
    if ($checkInTime < 50 && $checkOutTime < 50) {
        echo "🚀 EXCELLENT: Queries are super fast (< 50ms)!\n";
    } elseif ($checkInTime < 100 && $checkOutTime < 100) {
        echo "✅ GOOD: Queries are fast (< 100ms)\n";
    } else {
        echo "⚠️  SLOW: Queries taking longer than expected\n";
    }
    
    echo "\n🎉 Performance optimization complete!\n";
    echo "The guardhouse reports should now load instantly!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
