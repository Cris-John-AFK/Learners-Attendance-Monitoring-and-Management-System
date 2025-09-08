<?php

require_once __DIR__ . '/lamms-backend/vendor/autoload.php';

// Load Laravel app
$app = require_once __DIR__ . '/lamms-backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== Checking attendance_records Table Structure ===\n\n";
    
    // Check table structure
    echo "1. Table structure:\n";
    $columns = DB::select("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'attendance_records'
        ORDER BY ordinal_position
    ");
    
    foreach ($columns as $column) {
        echo "   - {$column->column_name} ({$column->data_type}) " . 
             ($column->is_nullable === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "\n";
    
    // Check sample data
    echo "2. Sample data:\n";
    $sampleData = DB::table('attendance_records')->limit(3)->get();
    foreach ($sampleData as $record) {
        echo "   Record: " . json_encode($record) . "\n";
    }
    echo "\n";
    
    // Check related tables
    echo "3. Checking attendance_sessions table:\n";
    $sessionColumns = DB::select("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'attendance_sessions'
        ORDER BY ordinal_position
    ");
    
    foreach ($sessionColumns as $column) {
        echo "   - {$column->column_name} ({$column->data_type}) " . 
             ($column->is_nullable === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "\n";
    
    // Check sample session data
    echo "4. Sample session data:\n";
    $sessionData = DB::table('attendance_sessions')->limit(3)->get();
    foreach ($sessionData as $session) {
        echo "   Session: " . json_encode($session) . "\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
