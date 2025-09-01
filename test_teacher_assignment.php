<?php

echo "Testing teacher assignment API...\n\n";

// Test teacher assignment to section 3
echo "Testing teacher assignment: POST /api/curriculums/1/grades/1/sections/3/teacher\n";

$url = 'http://localhost:8000/api/curriculums/1/grades/1/sections/3/teacher';
$data = json_encode(['teacher_id' => 2]); // Assign teacher ID 2 (Roberto Roberta)

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        'content' => $data
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ Teacher assignment API failed\n";
    $error = error_get_last();
    echo "Error: " . $error['message'] . "\n";
} else {
    $responseData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Teacher assignment API response:\n";
        echo "Success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . $responseData['message'] . "\n";
        
        if (isset($responseData['section'])) {
            $section = $responseData['section'];
            echo "Section ID: " . $section['id'] . "\n";
            echo "Section Name: " . $section['name'] . "\n";
            echo "Homeroom Teacher ID: " . ($section['homeroom_teacher_id'] ?? 'null') . "\n";
            
            if (isset($section['homeroom_teacher'])) {
                $teacher = $section['homeroom_teacher'];
                echo "Teacher Name: " . $teacher['first_name'] . ' ' . $teacher['last_name'] . "\n";
            }
        }
    } else {
        echo "❌ Invalid JSON response\n";
        echo "Response: " . $response . "\n";
    }
}

echo "\n";

// Verify the assignment by checking the section
echo "Verifying assignment by checking section 3...\n";
$verifyUrl = 'http://localhost:8000/api/sections/grade/1?curriculum_id=1';
$verifyResponse = file_get_contents($verifyUrl);

if ($verifyResponse !== false) {
    $sections = json_decode($verifyResponse, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        foreach ($sections as $section) {
            if ($section['id'] == 3) {
                echo "Section 3 (Magaspang) homeroom_teacher_id: " . ($section['homeroom_teacher_id'] ?? 'null') . "\n";
                if (isset($section['homeroom_teacher'])) {
                    $teacher = $section['homeroom_teacher'];
                    echo "Teacher: " . $teacher['first_name'] . ' ' . $teacher['last_name'] . "\n";
                } else {
                    echo "No homeroom teacher assigned\n";
                }
                break;
            }
        }
    }
}

echo "\nTest complete.\n";
