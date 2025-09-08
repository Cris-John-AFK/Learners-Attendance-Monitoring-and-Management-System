<?php

// Test the actual API response structure
require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
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

echo "Testing API response structure...\n\n";

try {
    $controller = new \App\Http\Controllers\API\AttendanceSummaryController();
    
    // Test trends endpoint
    $request = new Request();
    $request->merge([
        'teacher_id' => 3,
        'period' => 'week',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    $response = $controller->getTeacherAttendanceTrends($request);
    $data = json_decode($response->getContent(), true);
    
    echo "=== TRENDS API RESPONSE STRUCTURE ===\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Data structure:\n";
    print_r($data['data']);
    
    // Test summary endpoint
    $request2 = new Request();
    $request2->merge([
        'teacher_id' => 3,
        'period' => 'month',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    $response2 = $controller->getTeacherAttendanceSummary($request2);
    $data2 = json_decode($response2->getContent(), true);
    
    echo "\n=== SUMMARY API RESPONSE STRUCTURE ===\n";
    echo "Success: " . ($data2['success'] ? 'true' : 'false') . "\n";
    echo "Data structure:\n";
    print_r($data2['data']);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
