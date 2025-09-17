<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;

// Test database connection and tables
try {
    echo "Testing database connection...\n";
    
    // Test grades table
    $grades = DB::table('grades')->where('is_active', true)->get();
    echo "Grades found: " . count($grades) . "\n";
    
    // Test attendance_statuses table
    try {
        $statuses = DB::table('attendance_statuses')->get();
        echo "Attendance statuses found: " . count($statuses) . "\n";
    } catch (Exception $e) {
        echo "Attendance statuses table not found: " . $e->getMessage() . "\n";
    }
    
    // Test attendance_records table
    try {
        $records = DB::table('attendance_records')->limit(5)->get();
        echo "Attendance records found: " . count($records) . "\n";
    } catch (Exception $e) {
        echo "Attendance records table issue: " . $e->getMessage() . "\n";
    }
    
    // Test student_details table
    try {
        $students = DB::table('student_details')->where('isActive', true)->limit(5)->get();
        echo "Active students found: " . count($students) . "\n";
    } catch (Exception $e) {
        echo "Student details table issue: " . $e->getMessage() . "\n";
    }
    
    // Test sections table
    try {
        $sections = DB::table('sections')->limit(5)->get();
        echo "Sections found: " . count($sections) . "\n";
    } catch (Exception $e) {
        echo "Sections table issue: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
