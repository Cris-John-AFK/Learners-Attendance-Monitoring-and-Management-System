<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Table Structure Debug ===\n";

// Check attendance_records table structure
echo "Attendance Records table columns:\n";
$columns = Schema::getColumnListing('attendance_records');
foreach($columns as $column) {
    echo "  - $column\n";
}

echo "\nSample attendance_records data:\n";
$records = DB::table('attendance_records')->limit(5)->get();
foreach($records as $record) {
    echo "Record: " . json_encode($record) . "\n";
}

// Check attendance_sessions table structure
echo "\nAttendance Sessions table columns:\n";
$sessionColumns = Schema::getColumnListing('attendance_sessions');
foreach($sessionColumns as $column) {
    echo "  - $column\n";
}

echo "\nSample attendance_sessions data:\n";
$sessions = DB::table('attendance_sessions')->limit(5)->get();
foreach($sessions as $session) {
    echo "Session: " . json_encode($session) . "\n";
}

?>
