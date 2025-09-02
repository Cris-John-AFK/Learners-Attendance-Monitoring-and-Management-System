<?php

require_once 'lamms-backend/vendor/autoload.php';

// Test grade creation API endpoint
$data = [
    'code' => 'K1',
    'name' => 'Kinder 1', 
    'level' => '0',
    'display_order' => 1,
    'description' => ''
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/grades');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo "Testing grade creation with data:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 422) {
    echo "\n422 Error - Validation failed. Checking existing grades...\n";
    
    // Check existing grades
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8000/api/grades');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $gradesResponse = curl_exec($ch2);
    $existingGrades = json_decode($gradesResponse, true);
    
    echo "Existing grades:\n";
    foreach ($existingGrades as $grade) {
        echo "- Code: {$grade['code']}, Name: {$grade['name']}, Level: {$grade['level']}\n";
    }
    
    curl_close($ch2);
}

curl_close($ch);

?>
