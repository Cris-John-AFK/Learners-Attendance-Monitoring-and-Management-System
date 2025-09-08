<?php

echo "=== TESTING ATTENDANCE MARKING - 422 ERROR DIAGNOSIS ===\n\n";

// Test the attendance marking endpoint that's causing 422 errors
$url = 'http://localhost:8000/api/attendance';
$testData = [
    "section_id" => 13,
    "subject_id" => 1,
    "teacher_id" => 1,
    "date" => "2025-09-04",
    "attendance" => [
        [
            "student_id" => 2,
            "attendance_status_id" => 1,
            "remarks" => "Present"
        ],
        [
            "student_id" => 3,
            "attendance_status_id" => 2,
            "remarks" => "Absent"
        ]
    ]
];

echo "Testing URL: {$url}\n";
echo "Data being sent:\n" . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: {$httpCode}\n";
echo "Response Body:\n{$response}\n\n";

if ($httpCode == 422) {
    echo "🔍 ANALYZING 422 ERROR:\n";
    $errorData = json_decode($response, true);
    
    if (isset($errorData['errors'])) {
        echo "Validation Errors Found:\n";
        foreach ($errorData['errors'] as $field => $messages) {
            echo "- {$field}: " . implode(', ', $messages) . "\n";
        }
    }
    
    if (isset($errorData['message'])) {
        echo "Error Message: " . $errorData['message'] . "\n";
    }
} elseif ($httpCode == 200) {
    echo " Attendance marking successful!\n";
} else {
    echo " Unexpected error code: {$httpCode}\n";
}

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

echo "Diagnosing API Error...\n\n";

try {
    // Test database connection
    $connection = DB::connection();
    $pdo = $connection->getPdo();
    echo " Database connection successful\n";

    // Check if attendance_statuses table exists
    $statusTableExists = DB::select("SELECT to_regclass('attendance_statuses') as exists");
    $hasStatusTable = $statusTableExists[0]->exists !== null;
    echo " Attendance statuses table exists: " . ($hasStatusTable ? 'Yes' : 'No') . "\n";

    // Check teacher assignments
    $assignments = DB::table('teacher_section_subject as tss')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
        ->where('tss.teacher_id', 3)
        ->select('tss.*', 's.name as section_name', 'sub.name as subject_name')
        ->get();
    
    echo " Teacher 3 assignments: " . count($assignments) . "\n";
    foreach ($assignments as $assignment) {
        echo "  - Section: {$assignment->section_name}, Subject: {$assignment->subject_name}\n";
    }

    // Check students for teacher
    $students = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->join('teacher_section_subject as tss', 'ss.section_id', '=', 'tss.section_id')
        ->where('tss.teacher_id', 3)
        ->where('tss.subject_id', 1)
        ->where('ss.is_active', true)
        ->where('sd.isActive', true)
        ->select('sd.id', 'sd.firstName', 'sd.lastName')
        ->distinct()
        ->get();
    
    echo " Students for teacher 3, subject 1: " . count($students) . "\n";

    // Test the attendance summary controller directly
    $controller = new \App\Http\Controllers\API\AttendanceSummaryController();
    
    // Create a mock request
    $request = new Request();
    $request->merge([
        'teacher_id' => 3,
        'period' => 'month',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);

    echo "\nTesting attendance summary endpoint...\n";
    $response = $controller->getTeacherAttendanceSummary($request);
    $statusCode = $response->getStatusCode();
    $content = $response->getContent();
    
    echo "Status Code: $statusCode\n";
    echo "Response: $content\n";

    // Test trends endpoint
    echo "\nTesting attendance trends endpoint...\n";
    $request2 = new Request();
    $request2->merge([
        'teacher_id' => 3,
        'period' => 'week',
        'view_type' => 'subject',
        'subject_id' => 1
    ]);
    
    $response2 = $controller->getTeacherAttendanceTrends($request2);
    $statusCode2 = $response2->getStatusCode();
    $content2 = $response2->getContent();
    
    echo "Status Code: $statusCode2\n";
    echo "Response: $content2\n";

} catch (Exception $e) {
    echo " Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}