<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing guardhouse API speed...\n";
    
    // Clear any existing cache
    cache()->flush();
    echo "✅ Cache cleared\n";
    
    // Test the live feed API performance
    $start = microtime(true);
    
    $today = today()->toDateString();
    
    // Simulate the exact query from the API
    $checkIns = DB::table('guardhouse_attendance')
        ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
        ->where('guardhouse_attendance.date', $today)
        ->where('guardhouse_attendance.record_type', 'check-in')
        ->orderBy('guardhouse_attendance.timestamp', 'desc')
        ->limit(50)
        ->select(
            'guardhouse_attendance.id',
            'guardhouse_attendance.student_id',
            DB::raw('student_details."firstName" || \' \' || student_details."lastName" as student_name'),
            'student_details.gradeLevel as grade_level',
            'student_details.section',
            'guardhouse_attendance.timestamp',
            'guardhouse_attendance.record_type'
        )
        ->get();
    
    $checkOuts = DB::table('guardhouse_attendance')
        ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
        ->where('guardhouse_attendance.date', $today)
        ->where('guardhouse_attendance.record_type', 'check-out')
        ->orderBy('guardhouse_attendance.timestamp', 'desc')
        ->limit(50)
        ->select(
            'guardhouse_attendance.id',
            'guardhouse_attendance.student_id',
            DB::raw('student_details."firstName" || \' \' || student_details."lastName" as student_name'),
            'student_details.gradeLevel as grade_level',
            'student_details.section',
            'guardhouse_attendance.timestamp',
            'guardhouse_attendance.record_type'
        )
        ->get();
    
    $counts = DB::table('guardhouse_attendance')
        ->where('date', $today)
        ->selectRaw('
            COUNT(CASE WHEN record_type = \'check-in\' THEN 1 END) as total_check_ins,
            COUNT(CASE WHEN record_type = \'check-out\' THEN 1 END) as total_check_outs
        ')
        ->first();
    
    $end = microtime(true);
    $queryTime = ($end - $start) * 1000;
    
    echo "\n📊 Performance Results:\n";
    echo "   - Total query time: {$queryTime}ms\n";
    echo "   - Check-ins found: {$checkIns->count()}\n";
    echo "   - Check-outs found: {$checkOuts->count()}\n";
    echo "   - Total check-ins today: {$counts->total_check_ins}\n";
    echo "   - Total check-outs today: {$counts->total_check_outs}\n";
    
    if ($queryTime < 50) {
        echo "🚀 EXCELLENT: Queries are lightning fast!\n";
    } elseif ($queryTime < 100) {
        echo "✅ GOOD: Queries are fast\n";
    } else {
        echo "⚠️  SLOW: Queries need more optimization\n";
    }
    
    echo "\n🎯 Expected frontend performance:\n";
    echo "   - First load (no cache): ~{$queryTime}ms per API call\n";
    echo "   - Subsequent loads (cached): ~5-10ms\n";
    echo "   - With parallel loading: ~{$queryTime}ms total (all 3 APIs)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
