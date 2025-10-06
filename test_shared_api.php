<?php
/**
 * LAMMS Shared API Test Script
 * 
 * This script tests all shared attendance API endpoints
 * Run this file to verify the API is working correctly
 */

echo "🧪 LAMMS Shared Attendance API Test Script\n";
echo "==========================================\n\n";

$baseUrl = 'http://localhost:8000/api/shared/attendance';
$testResults = [];

/**
 * Helper function to make API calls
 */
function callApi($url, $description) {
    echo "Testing: $description\n";
    echo "URL: $url\n";
    
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    if ($error) {
        echo "❌ FAILED: $error\n\n";
        return [
            'success' => false,
            'error' => $error,
            'response_time' => $responseTime
        ];
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "✅ SUCCESS (HTTP $httpCode) - Response time: {$responseTime}ms\n";
        
        // Show summary of response
        if (isset($data['meta']['total_students'])) {
            echo "   Total Students: {$data['meta']['total_students']}\n";
        }
        if (isset($data['data']['total_records'])) {
            echo "   Total Records: {$data['data']['total_records']}\n";
        }
        if (isset($data['data']['student_info']['name'])) {
            echo "   Student: {$data['data']['student_info']['name']}\n";
        }
        
        echo "\n";
        
        return [
            'success' => true,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'data' => $data
        ];
    } else {
        echo "❌ FAILED (HTTP $httpCode)\n";
        echo "Response: " . substr($response, 0, 200) . "...\n\n";
        
        return [
            'success' => false,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'response' => $response
        ];
    }
}

// Test 1: Get All Students
echo "TEST 1: Get All Students with Attendance\n";
echo "----------------------------------------\n";
$testResults['all_students'] = callApi(
    "$baseUrl/students?date_from=2025-09-01&date_to=2025-10-06",
    "Get all students with attendance records"
);

// Test 2: Get Specific Student (using ID 14 from documentation)
echo "TEST 2: Get Specific Student Details\n";
echo "-----------------------------------\n";
$testResults['specific_student'] = callApi(
    "$baseUrl/students/14?date_from=2025-07-01&date_to=2025-10-06",
    "Get student ID 14 with attendance history"
);

// Test 3: Get Attendance Summary
echo "TEST 3: Get Attendance Summary Statistics\n";
echo "----------------------------------------\n";
$testResults['summary'] = callApi(
    "$baseUrl/summary?date_from=2025-09-01&date_to=2025-10-06",
    "Get overall attendance statistics"
);

// Test 4: Get Daily Attendance
echo "TEST 4: Get Daily Attendance Report\n";
echo "----------------------------------\n";
$testResults['daily'] = callApi(
    "$baseUrl/daily?date=2025-10-06",
    "Get attendance for today"
);

// Test 5: Filter by Section
echo "TEST 5: Get Students Filtered by Section\n";
echo "---------------------------------------\n";
$testResults['by_section'] = callApi(
    "$baseUrl/students?section_id=1&date_from=2025-09-01&date_to=2025-10-06",
    "Get students in section 1"
);

// Summary Report
echo "\n";
echo "📊 TEST SUMMARY REPORT\n";
echo "======================\n\n";

$totalTests = count($testResults);
$passedTests = 0;
$failedTests = 0;
$totalResponseTime = 0;

foreach ($testResults as $testName => $result) {
    if ($result['success']) {
        $passedTests++;
    } else {
        $failedTests++;
    }
    $totalResponseTime += $result['response_time'];
}

echo "Total Tests: $totalTests\n";
echo "Passed: ✅ $passedTests\n";
echo "Failed: ❌ $failedTests\n";
echo "Average Response Time: " . round($totalResponseTime / $totalTests, 2) . "ms\n\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL TESTS PASSED! API is working correctly.\n";
    echo "You can share this API with your groupmates.\n\n";
    echo "📁 Share these files:\n";
    echo "   - SHARED_API_DOCUMENTATION.md (Complete API docs)\n";
    echo "   - LAMMS_Shared_API_Postman_Collection.json (Postman collection)\n";
    echo "   - api_test_page.html (Visual test interface)\n";
    echo "   - SHARE_WITH_GROUPMATES_README.md (Quick start guide)\n";
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "Please check:\n";
    echo "   1. Is the Laravel backend running? (php artisan serve)\n";
    echo "   2. Is the database populated with test data?\n";
    echo "   3. Are the routes properly configured?\n";
    echo "   4. Check Laravel logs for errors\n";
}

echo "\n";
echo "🔗 Quick Links:\n";
echo "   - Backend: http://localhost:8000\n";
echo "   - API Base: http://localhost:8000/api/shared/attendance\n";
echo "   - Test Page: api_test_page.html (open in browser)\n";
echo "\n";

// Optional: Save detailed results to file
$resultsFile = 'api_test_results.json';
file_put_contents($resultsFile, json_encode($testResults, JSON_PRETTY_PRINT));
echo "📝 Detailed results saved to: $resultsFile\n";
