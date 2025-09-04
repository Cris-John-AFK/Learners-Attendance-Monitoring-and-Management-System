<?php

// Test script to verify Kinder section assignment functionality
require_once 'lamms-backend/vendor/autoload.php';

// Test the grade level matching logic that was implemented in EnrollmentController
function testGradeLevelMatching() {
    echo "Testing Grade Level Matching Logic for Kinder Sections\n";
    echo "=====================================================\n\n";
    
    // Test cases for different grade formats
    $testCases = [
        ['student_grade' => 'K', 'section_grade' => 'K', 'expected' => true],
        ['student_grade' => 'Kinder', 'section_grade' => 'K', 'expected' => true],
        ['student_grade' => 'Kinder 1', 'section_grade' => 'K', 'expected' => true],
        ['student_grade' => 'Kinder 2', 'section_grade' => 'K', 'expected' => true],
        ['student_grade' => 'K', 'section_grade' => 'Kinder', 'expected' => true],
        ['student_grade' => 'Grade 1', 'section_grade' => '1', 'expected' => true],
        ['student_grade' => '1', 'section_grade' => 'Grade 1', 'expected' => true],
        ['student_grade' => 'K', 'section_grade' => '1', 'expected' => false],
        ['student_grade' => 'Grade 2', 'section_grade' => 'K', 'expected' => false],
    ];
    
    foreach ($testCases as $i => $test) {
        $result = gradesMatch($test['student_grade'], $test['section_grade']);
        $status = $result === $test['expected'] ? '✓ PASS' : '✗ FAIL';
        
        echo "Test " . ($i + 1) . ": {$status}\n";
        echo "  Student Grade: '{$test['student_grade']}'\n";
        echo "  Section Grade: '{$test['section_grade']}'\n";
        echo "  Expected: " . ($test['expected'] ? 'true' : 'false') . "\n";
        echo "  Actual: " . ($result ? 'true' : 'false') . "\n\n";
    }
}

// Replicate the grade matching logic from EnrollmentController
function gradesMatch($studentGrade, $sectionGrade) {
    // Normalize both grades for comparison
    $normalizedStudentGrade = normalizeGrade($studentGrade);
    $normalizedSectionGrade = normalizeGrade($sectionGrade);
    
    return $normalizedStudentGrade === $normalizedSectionGrade;
}

function normalizeGrade($grade) {
    if (empty($grade)) return '';
    
    $grade = trim(strtolower($grade));
    
    // Handle Kinder variations
    if (in_array($grade, ['k', 'kinder', 'kinder 1', 'kinder 2', 'kindergarten'])) {
        return 'k';
    }
    
    // Handle "Grade X" format
    if (preg_match('/^grade\s*(\d+)$/', $grade, $matches)) {
        return $matches[1];
    }
    
    // Handle numeric grades
    if (is_numeric($grade)) {
        return $grade;
    }
    
    return $grade;
}

// Test API endpoint availability
function testAPIEndpoint() {
    echo "Testing API Endpoint Availability\n";
    echo "=================================\n\n";
    
    $testStudentId = 1; // Test with a sample student ID
    $url = "http://127.0.0.1:8000/api/enrollments/{$testStudentId}/available-sections";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            'timeout' => 10
        ]
    ]);
    
    echo "Testing URL: {$url}\n";
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "✗ API endpoint not accessible\n";
        echo "Make sure Laravel server is running on http://127.0.0.1:8000\n\n";
    } else {
        echo "✓ API endpoint accessible\n";
        $data = json_decode($response, true);
        if ($data) {
            echo "Response structure looks valid\n";
            if (isset($data['success'])) {
                echo "Success field: " . ($data['success'] ? 'true' : 'false') . "\n";
            }
            if (isset($data['data'])) {
                echo "Available sections count: " . count($data['data']) . "\n";
            }
        }
        echo "\n";
    }
}

// Run tests
echo "KINDER SECTION ASSIGNMENT FUNCTIONALITY TEST\n";
echo "============================================\n\n";

testGradeLevelMatching();
testAPIEndpoint();

echo "Test completed!\n";
echo "\nTo test the full functionality:\n";
echo "1. Open http://localhost:5175 in your browser\n";
echo "2. Navigate to Admin > Enrollment\n";
echo "3. Create or select a Kinder student\n";
echo "4. Click 'Assign Section' button\n";
echo "5. Verify that Kinder sections (Joy, Hope, Love) appear in the dropdown\n";

?>
