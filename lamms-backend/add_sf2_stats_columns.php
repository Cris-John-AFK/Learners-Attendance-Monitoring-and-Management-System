<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 Adding attendance statistics columns to submitted_sf2_reports table...\n";

try {
    // Add columns if they don't exist
    $columns = [
        'total_students' => 'INTEGER DEFAULT 0',
        'present_today' => 'INTEGER DEFAULT 0', 
        'absent_today' => 'INTEGER DEFAULT 0',
        'attendance_rate' => 'DECIMAL(5,2) DEFAULT 0.00'
    ];

    foreach ($columns as $columnName => $columnDef) {
        try {
            DB::statement("ALTER TABLE submitted_sf2_reports ADD COLUMN IF NOT EXISTS {$columnName} {$columnDef}");
            echo "✅ Added column: {$columnName}\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "ℹ️  Column {$columnName} already exists\n";
            } else {
                echo "❌ Error adding {$columnName}: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n📊 Testing column addition...\n";
    
    // Test if we can query the new columns
    $testQuery = DB::table('submitted_sf2_reports')
        ->select('id', 'section_name', 'total_students', 'present_today', 'absent_today', 'attendance_rate')
        ->first();
    
    if ($testQuery) {
        echo "✅ Columns added successfully! Test record:\n";
        echo "   Section: {$testQuery->section_name}\n";
        echo "   Total Students: {$testQuery->total_students}\n";
        echo "   Present Today: {$testQuery->present_today}\n";
        echo "   Absent Today: {$testQuery->absent_today}\n";
        echo "   Attendance Rate: {$testQuery->attendance_rate}%\n";
    } else {
        echo "ℹ️  No records found to test with\n";
    }

    echo "\n🎉 Database update completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
