<?php

require_once 'lamms-backend/vendor/autoload.php';

echo "Getting curriculum_grade table IDs...\n\n";

// Try to access curriculum_grade table directly
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades/1/relationship');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Relationship endpoint (HTTP $httpCode): $response\n\n";

// Try grade 2 relationship
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades/2/relationship');
$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Grade 2 relationship endpoint (HTTP $httpCode2): $response2\n\n";

// Check what sections exist to see their curriculum_grade_id
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/sections');
$sectionsResponse = curl_exec($ch);
$sectionsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Existing sections (HTTP $sectionsHttpCode):\n";
$sections = json_decode($sectionsResponse, true);
if (is_array($sections)) {
    foreach ($sections as $section) {
        echo "- Section ID: {$section['id']}, Name: {$section['name']}, curriculum_grade_id: {$section['curriculum_grade_id']}\n";
    }
}

curl_close($ch);

?>
