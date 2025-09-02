<?php

require_once 'lamms-backend/vendor/autoload.php';

// Test section creation API endpoint
$data = [
    'name' => 'Section B',
    'description' => '',
    'capacity' => 40,
    'curriculum_grade_id' => null
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/sections');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo "Testing section creation with data:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 422) {
    echo "\n422 Error - Validation failed. Checking curriculum_grade table...\n";
    
    // Check curriculum_grade table
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $gradesResponse = curl_exec($ch2);
    $curriculumGrades = json_decode($gradesResponse, true);
    
    echo "Available curriculum_grade records:\n";
    if (is_array($curriculumGrades)) {
        foreach ($curriculumGrades as $cg) {
            echo "- ID: {$cg['id']}, Grade ID: {$cg['grade_id']}, Curriculum ID: {$cg['curriculum_id']}\n";
        }
        
        // Try with valid curriculum_grade_id
        if (!empty($curriculumGrades)) {
            $validData = $data;
            $validData['curriculum_grade_id'] = $curriculumGrades[0]['id'];
            
            echo "\nTrying again with valid curriculum_grade_id: {$validData['curriculum_grade_id']}\n";
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validData));
            $response2 = curl_exec($ch);
            $httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            echo "HTTP Status: $httpCode2\n";
            echo "Response: $response2\n";
        }
    }
    
    curl_close($ch2);
}

curl_close($ch);

?>
