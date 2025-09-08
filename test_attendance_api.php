<?php

require_once 'lamms-backend/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'lamms-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;

try {
    echo "=== TESTING NEW ATTENDANCE API ENDPOINTS ===\n\n";
    
    // Test 1: Get students for teacher's section and subject
    echo "1. Testing GET /api/attendance-sessions/students...\n";
    
    $request = Request::create('/api/attendance-sessions/students', 'GET', [
        'teacher_id' => 3,
        'section_id' => 3, 
        'subject_id' => 1
    ]);
    
    $response = $kernel->handle($request);
    $content = $response->getContent();
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Response: " . $content . "\n\n";
    
    // Test 2: Create attendance session
    echo "2. Testing POST /api/attendance-sessions...\n";
    
    $sessionRequest = Request::create('/api/attendance-sessions', 'POST', [
        'teacher_id' => 3,
        'section_id' => 3,
        'subject_id' => 1,
        'session_date' => date('Y-m-d'),
        'session_start_time' => date('H:i:s'),
        'session_type' => 'regular',
        'metadata' => []
    ]);
    
    $sessionResponse = $kernel->handle($sessionRequest);
    $sessionContent = $sessionResponse->getContent();
    
    echo "Status: " . $sessionResponse->getStatusCode() . "\n";
    echo "Response: " . $sessionContent . "\n\n";
    
    if ($sessionResponse->getStatusCode() === 201) {
        $sessionData = json_decode($sessionContent, true);
        $sessionId = $sessionData['data']['id'] ?? null;
        
        if ($sessionId) {
            echo "3. Testing POST /api/attendance-sessions/{$sessionId}/attendance...\n";
            
            // Test 3: Mark attendance
            $attendanceRequest = Request::create("/api/attendance-sessions/{$sessionId}/attendance", 'POST', [
                'attendance' => [
                    ['student_id' => 3, 'attendance_status_id' => 1, 'remarks' => 'Present'],
                    ['student_id' => 4, 'attendance_status_id' => 1, 'remarks' => 'Present'],
                    ['student_id' => 5, 'attendance_status_id' => 2, 'remarks' => 'Absent'],
                    ['student_id' => 6, 'attendance_status_id' => 1, 'remarks' => 'Present']
                ]
            ]);
            
            $attendanceResponse = $kernel->handle($attendanceRequest);
            echo "Status: " . $attendanceResponse->getStatusCode() . "\n";
            echo "Response: " . $attendanceResponse->getContent() . "\n\n";
            
            // Test 4: Complete session
            echo "4. Testing POST /api/attendance-sessions/{$sessionId}/complete...\n";
            
            $completeRequest = Request::create("/api/attendance-sessions/{$sessionId}/complete", 'POST');
            $completeResponse = $kernel->handle($completeRequest);
            
            echo "Status: " . $completeResponse->getStatusCode() . "\n";
            echo "Response: " . $completeResponse->getContent() . "\n\n";
        }
    }
    
    echo "✅ NEW ATTENDANCE API TESTING COMPLETED!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
