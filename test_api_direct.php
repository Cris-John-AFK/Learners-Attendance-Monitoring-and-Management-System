<?php

// Direct test of the API endpoints without going through HTTP
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

echo "Testing API endpoints directly...\n\n";

try {
    // Test summary endpoint
    $controller = new \App\Http\Controllers\API\AttendanceSummaryController();
    
    $request = new Request();
    $request->merge([
        'teacher_id' => 3,
        'period' => 'month',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    $response = $controller->getTeacherAttendanceSummary($request);
    echo "Summary API Response:\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n\n";

    // Test trends endpoint
    $request2 = new Request();
    $request2->merge([
        'teacher_id' => 3,
        'period' => 'week',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    $response2 = $controller->getTeacherAttendanceTrends($request2);
    echo "Trends API Response:\n";
    echo "Status: " . $response2->getStatusCode() . "\n";
    echo "Content: " . $response2->getContent() . "\n\n";

    // Parse and display the data nicely
    $summaryData = json_decode($response->getContent(), true);
    if ($summaryData['success']) {
        echo "=== SUMMARY DATA ===\n";
        echo "Total Students: " . $summaryData['data']['total_students'] . "\n";
        echo "Average Attendance: " . $summaryData['data']['average_attendance'] . "%\n";
        echo "Students with Warning: " . $summaryData['data']['students_with_warning'] . "\n";
        echo "Students with Critical: " . $summaryData['data']['students_with_critical'] . "\n";
        
        echo "\nStudent Details:\n";
        foreach ($summaryData['data']['students'] as $student) {
            echo "- {$student['first_name']} {$student['last_name']}: {$student['total_absences']} total absences, {$student['recent_absences']} recent\n";
        }
    }

    $trendsData = json_decode($response2->getContent(), true);
    if ($trendsData['success']) {
        echo "\n=== TRENDS DATA ===\n";
        echo "Labels: " . implode(', ', $trendsData['data']['labels']) . "\n";
        foreach ($trendsData['data']['datasets'] as $dataset) {
            echo "{$dataset['label']}: " . implode(', ', $dataset['data']) . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
