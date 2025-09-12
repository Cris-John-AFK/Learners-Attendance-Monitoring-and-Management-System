<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\API\AttendanceSessionController;

// Create a mock request
$request = new Request();
$request->merge([
    'section_id' => 3,
    'week_start' => '2025-09-12'
]);

try {
    $controller = new AttendanceSessionController();
    $response = $controller->getWeeklyReport($request);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content: " . $response->getContent() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
