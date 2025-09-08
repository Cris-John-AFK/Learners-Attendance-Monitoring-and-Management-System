<?php

require_once __DIR__ . '/lamms-backend/vendor/autoload.php';

// Load Laravel app
$app = require_once __DIR__ . '/lamms-backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\AttendanceSummaryController;
use Illuminate\Http\Request;

try {
    echo "=== Testing AttendanceSummaryController ===\n\n";
    
    // Test database connection
    echo "1. Testing database connection...\n";
    $connection = DB::connection()->getPdo();
    echo "✓ Database connection successful\n\n";
    
    // Check if attendance_records table exists
    echo "2. Checking attendance_records table...\n";
    $tableExists = DB::select("SELECT to_regclass('attendance_records') as exists");
    if ($tableExists[0]->exists) {
        echo "✓ attendance_records table exists\n";
        
        $recordCount = DB::table('attendance_records')->count();
        echo "   Records count: $recordCount\n\n";
    } else {
        echo "✗ attendance_records table does not exist\n\n";
    }
    
    // Test controller instantiation
    echo "3. Testing controller instantiation...\n";
    try {
        $controller = new AttendanceSummaryController();
        echo "✓ AttendanceSummaryController instantiated successfully\n\n";
    } catch (\Exception $e) {
        echo "✗ Failed to instantiate controller: " . $e->getMessage() . "\n\n";
        exit(1);
    }
    
    // Test getTeacherAttendanceSummary method
    echo "4. Testing getTeacherAttendanceSummary method...\n";
    try {
        // Create a mock request
        $request = new Request([
            'teacher_id' => 3,
            'period' => 'week',
            'view_type' => 'subject',
            'subject_id' => 1
        ]);
        
        $response = $controller->getTeacherAttendanceSummary($request);
        $responseData = json_decode($response->getContent(), true);
        
        echo "✓ Method executed successfully\n";
        echo "Response status: " . $response->getStatusCode() . "\n";
        echo "Response data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";
        
    } catch (\Exception $e) {
        echo "✗ Method failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    }
    
    // Test getAttendanceTrends method
    echo "5. Testing getAttendanceTrends method...\n";
    try {
        // Create a mock request
        $request = new Request([
            'teacher_id' => 3,
            'period' => 'week',
            'view_type' => 'subject',
            'subject_id' => 1
        ]);
        
        $response = $controller->getAttendanceTrends($request);
        $responseData = json_decode($response->getContent(), true);
        
        echo "✓ Method executed successfully\n";
        echo "Response status: " . $response->getStatusCode() . "\n";
        echo "Response data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";
        
    } catch (\Exception $e) {
        echo "✗ Method failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    }
    
    echo "=== Test Complete ===\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
