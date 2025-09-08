<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

Config::set('database.default', 'pgsql');
Config::set('database.connections.pgsql', [
    'driver' => 'pgsql',
    'host' => 'localhost',
    'port' => '5432',
    'database' => 'lamms_db',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

echo "Checking attendance table structures...\n\n";

// Check attendances table
$attendances = DB::table('attendances')->orderBy('id', 'desc')->limit(5)->get();
echo "Recent attendances table records: " . count($attendances) . "\n";
foreach ($attendances as $record) {
    echo "  ID: {$record->id}, Student: {$record->student_id}, Status: {$record->status}, Date: {$record->date}\n";
}

echo "\n";

// Check attendance_records table  
$attendance_records = DB::table('attendance_records')->orderBy('id', 'desc')->limit(5)->get();
echo "Recent attendance_records table records: " . count($attendance_records) . "\n";
foreach ($attendance_records as $record) {
    echo "  ID: {$record->id}, Student: {$record->student_id}, Session: {$record->attendance_session_id}, Status: {$record->attendance_status_id}\n";
}

echo "\n";

// Check attendance_sessions table
$attendance_sessions = DB::table('attendance_sessions')->orderBy('id', 'desc')->limit(5)->get();
echo "Recent attendance_sessions table records: " . count($attendance_sessions) . "\n";
foreach ($attendance_sessions as $record) {
    echo "  ID: {$record->id}, Teacher: {$record->teacher_id}, Section: {$record->section_id}, Date: {$record->session_date}\n";
}

echo "\n";

// Check which table has the most recent data
$attendances_count = DB::table('attendances')->count();
$attendance_records_count = DB::table('attendance_records')->count();
$attendance_sessions_count = DB::table('attendance_sessions')->count();

echo "Table counts:\n";
echo "- attendances: {$attendances_count}\n";
echo "- attendance_records: {$attendance_records_count}\n";
echo "- attendance_sessions: {$attendance_sessions_count}\n";
