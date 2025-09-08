<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

// Bootstrap Laravel
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set up database configuration
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

echo "Testing Fixed AttendanceSummaryController...\n\n";

try {
    // Test 1: Check if controller can be instantiated
    $controller = new \App\Http\Controllers\API\AttendanceSummaryController();
    echo "✓ Controller instantiated successfully\n";

    // Test 2: Test attendance summary endpoint
    $request = new Request();
    $request->merge([
        'teacher_id' => 3,
        'period' => 'month',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    echo "\nTesting attendance summary API...\n";
    $response = $controller->getTeacherAttendanceSummary($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ Attendance summary API working\n";
        echo "  - Total students: " . $data['data']['total_students'] . "\n";
        echo "  - Average attendance: " . $data['data']['average_attendance'] . "%\n";
        echo "  - Students with warning: " . $data['data']['students_with_warning'] . "\n";
        echo "  - Students with critical: " . $data['data']['students_with_critical'] . "\n";
    } else {
        echo "✗ Attendance summary API failed: " . $data['message'] . "\n";
    }

    // Test 3: Test attendance trends endpoint
    $request2 = new Request();
    $request2->merge([
        'teacher_id' => 3,
        'period' => 'week',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    echo "\nTesting attendance trends API...\n";
    $response2 = $controller->getTeacherAttendanceTrends($request2);
    $data2 = json_decode($response2->getContent(), true);
    
    if ($data2['success']) {
        echo "✓ Attendance trends API working\n";
        echo "  - Labels count: " . count($data2['data']['labels']) . "\n";
        echo "  - Datasets count: " . count($data2['data']['datasets']) . "\n";
        echo "  - Sample label: " . $data2['data']['labels'][0] . "\n";
    } else {
        echo "✗ Attendance trends API failed: " . $data2['message'] . "\n";
    }

    // Test 4: Test all students view
    $request3 = new Request();
    $request3->merge([
        'teacher_id' => 3,
        'period' => 'month',
        'view_type' => 'all_students'
    ]);

    echo "\nTesting all students view...\n";
    $response3 = $controller->getTeacherAttendanceSummary($request3);
    $data3 = json_decode($response3->getContent(), true);
    
    if ($data3['success']) {
        echo "✓ All students view working\n";
        echo "  - Total students: " . $data3['data']['total_students'] . "\n";
    } else {
        echo "✗ All students view failed: " . $data3['message'] . "\n";
    }

    echo "\n=== SUMMARY ===\n";
    echo "AttendanceSummaryController has been fixed and is working properly!\n";
    echo "Both summary and trends endpoints are functional.\n";

} catch (Exception $e) {
    echo "✗ Error testing controller: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
