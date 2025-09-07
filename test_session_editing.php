<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\API\AttendanceSessionController;

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Session Editing Workflow ===\n\n";

$controller = new AttendanceSessionController();

try {
    // Step 1: Create a test session
    echo "1. Creating test session...\n";
    $createRequest = new Request([
        'teacher_id' => 1,
        'section_id' => 13,
        'subject_id' => null, // Homeroom
        'session_date' => date('Y-m-d'),
        'session_start_time' => '08:00:00',
        'session_type' => 'regular'
    ]);
    
    $sessionResponse = $controller->createSession($createRequest);
    $sessionData = json_decode($sessionResponse->getContent(), true);
    
    if (isset($sessionData['session']) && isset($sessionData['session']['id'])) {
        $sessionId = $sessionData['session']['id'];
        echo "✅ Session created successfully. ID: {$sessionId}\n\n";
        
        // Step 2: Mark some attendance
        echo "2. Marking attendance...\n";
        $attendanceRequest = new Request([
            'attendance' => [
                [
                    'student_id' => 3,
                    'attendance_status_id' => 1, // Present
                    'remarks' => 'On time',
                    'marking_method' => 'manual'
                ],
                [
                    'student_id' => 2,
                    'attendance_status_id' => 2, // Absent
                    'remarks' => 'Sick leave',
                    'marking_method' => 'manual'
                ]
            ]
        ]);
        
        $attendanceResponse = $controller->markSessionAttendance($attendanceRequest, $sessionId);
        $attendanceData = json_decode($attendanceResponse->getContent(), true);
        
        echo "Attendance response: " . $attendanceResponse->getContent() . "\n";
        
        if (isset($attendanceData['message']) && strpos($attendanceData['message'], 'successfully') !== false) {
            echo "✅ Attendance marked successfully\n\n";
            
            // Step 3: Complete the session
            echo "3. Completing session...\n";
            $completeResponse = $controller->completeSession($sessionId);
            $completeData = json_decode($completeResponse->getContent(), true);
            
            echo "Complete response: " . $completeResponse->getContent() . "\n";
            
            if (isset($completeData['message']) && strpos($completeData['message'], 'successfully') !== false) {
                echo "✅ Session completed successfully\n\n";
                
                // Step 4: Test session editing
                echo "4. Testing session editing...\n";
                $editRequest = new Request([
                    'edit_reason' => 'correction',
                    'edit_notes' => 'Student 2 was actually present but arrived late',
                    'session_data' => [
                        'session_end_time' => '09:15:00' // Extend session time
                    ],
                    'attendance_records' => [
                        [
                            'student_id' => 2,
                            'attendance_status_id' => 3, // Late instead of Absent
                            'remarks' => 'Arrived 15 minutes late'
                        ]
                    ]
                ]);
                
                $editResponse = $controller->editSession($editRequest, $sessionId);
                $editData = json_decode($editResponse->getContent(), true);
                
                echo "Edit response: " . $editResponse->getContent() . "\n";
                
                if (isset($editData['message']) && strpos($editData['message'], 'successfully') !== false) {
                    echo "✅ Session edited successfully\n";
                    echo "   Session version: {$editData['session']['version']}\n\n";
                    
                    // Step 5: Test session history
                    echo "5. Testing session history...\n";
                    $historyResponse = $controller->getSessionHistory($sessionId);
                    $historyData = json_decode($historyResponse->getContent(), true);
                    
                    echo "History response: " . $historyResponse->getContent() . "\n";
                    
                    if (isset($historyData['message']) && strpos($historyData['message'], 'successfully') !== false) {
                        echo "✅ Session history retrieved successfully\n";
                        echo "   Total versions: " . count($historyData['data']['versions']) . "\n";
                        echo "   Audit logs: " . count($historyData['data']['audit_logs']) . "\n\n";
                        
                        // Step 6: Test session summary
                        echo "6. Testing session summary...\n";
                        $summaryResponse = $controller->getSessionSummary($sessionId);
                        $summaryData = json_decode($summaryResponse->getContent(), true);
                        
                        if ($summaryData['success']) {
                            echo "✅ Session summary retrieved successfully\n";
                            echo "   Total students: {$summaryData['data']['total_students']}\n";
                            echo "   Present: {$summaryData['data']['present_count']}\n";
                            echo "   Absent: {$summaryData['data']['absent_count']}\n";
                            echo "   Late: {$summaryData['data']['late_count']}\n\n";
                            
                            echo "🎉 ALL TESTS PASSED! Session editing workflow is fully functional.\n";
                        } else {
                            echo "❌ Session summary failed: " . ($summaryData['message'] ?? 'Unknown error') . "\n";
                        }
                    } else {
                        echo "❌ Session history failed: " . ($historyData['message'] ?? 'Unknown error') . "\n";
                    }
                } else {
                    echo "❌ Session editing failed: " . ($editData['message'] ?? 'Unknown error') . "\n";
                }
            } else {
                echo "❌ Session completion failed: " . ($completeData['message'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "❌ Attendance marking failed: " . ($attendanceData['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ Session creation failed: " . ($sessionData['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
