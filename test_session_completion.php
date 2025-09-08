<?php
// Test script to debug session completion issue
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Database configuration
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'database' => 'lamms_db',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "=== Testing Session Completion Issue ===\n\n";
    
    // 1. Check if attendance_sessions table exists
    echo "1. Checking if attendance_sessions table exists...\n";
    $tableExists = DB::schema()->hasTable('attendance_sessions');
    echo "Table exists: " . ($tableExists ? "YES" : "NO") . "\n\n";
    
    if (!$tableExists) {
        echo "ERROR: attendance_sessions table does not exist!\n";
        echo "Please run: php artisan migrate\n";
        exit(1);
    }
    
    // 2. Check table structure
    echo "2. Checking table structure...\n";
    $columns = DB::select("SELECT column_name, data_type, is_nullable 
                          FROM information_schema.columns 
                          WHERE table_name = 'attendance_sessions' 
                          ORDER BY ordinal_position");
    
    foreach ($columns as $column) {
        echo "- {$column->column_name}: {$column->data_type} (" . 
             ($column->is_nullable === 'YES' ? 'nullable' : 'not null') . ")\n";
    }
    echo "\n";
    
    // 3. Check for existing sessions
    echo "3. Checking existing sessions...\n";
    $sessions = DB::table('attendance_sessions')->get();
    echo "Total sessions: " . count($sessions) . "\n";
    
    foreach ($sessions as $session) {
        echo "- Session ID: {$session->id}, Status: {$session->status}, " .
             "Teacher: {$session->teacher_id}, Date: {$session->session_date}\n";
    }
    echo "\n";
    
    // 4. Check for session ID 5 specifically
    echo "4. Checking session ID 5 (from error)...\n";
    $session5 = DB::table('attendance_sessions')->where('id', 5)->first();
    
    if ($session5) {
        echo "Session 5 found:\n";
        echo "- Status: {$session5->status}\n";
        echo "- Teacher ID: {$session5->teacher_id}\n";
        echo "- Section ID: {$session5->section_id}\n";
        echo "- Subject ID: {$session5->subject_id}\n";
        echo "- Date: {$session5->session_date}\n";
        echo "- Start Time: {$session5->session_start_time}\n";
        echo "- End Time: {$session5->session_end_time}\n";
        echo "- Completed At: {$session5->completed_at}\n";
    } else {
        echo "Session ID 5 NOT FOUND!\n";
    }
    echo "\n";
    
    // 5. Check unique constraints
    echo "5. Checking unique constraints...\n";
    $constraints = DB::select("SELECT conname, pg_get_constraintdef(c.oid) as definition
                              FROM pg_constraint c
                              JOIN pg_class t ON c.conrelid = t.oid
                              WHERE t.relname = 'attendance_sessions' AND contype = 'u'");
    
    foreach ($constraints as $constraint) {
        echo "- {$constraint->conname}: {$constraint->definition}\n";
    }
    echo "\n";
    
    // 6. Test completion logic
    if ($session5 && $session5->status === 'active') {
        echo "6. Testing completion of session 5...\n";
        try {
            DB::table('attendance_sessions')
                ->where('id', 5)
                ->update([
                    'status' => 'completed',
                    'session_end_time' => date('H:i:s'),
                    'completed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            echo "SUCCESS: Session 5 completed successfully!\n";
        } catch (Exception $e) {
            echo "ERROR completing session 5: " . $e->getMessage() . "\n";
        }
    } else {
        echo "6. Session 5 is not active or doesn't exist, skipping completion test.\n";
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
