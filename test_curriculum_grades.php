<?php

require_once 'lamms-backend/vendor/autoload.php';

echo "Testing curriculum-grade relationships...\n\n";

// Check all grades
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/grades');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$gradesResponse = curl_exec($ch);
$grades = json_decode($gradesResponse, true);

echo "All available grades:\n";
foreach ($grades as $grade) {
    echo "- ID: {$grade['id']}, Code: {$grade['code']}, Name: {$grade['name']}\n";
}

// Check curriculum grades relationship
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades');
$curriculumGradesResponse = curl_exec($ch);
$curriculumGrades = json_decode($curriculumGradesResponse, true);

echo "\nCurriculum-Grade relationships for curriculum 1:\n";
if (is_array($curriculumGrades) && !empty($curriculumGrades)) {
    foreach ($curriculumGrades as $cg) {
        echo "- Curriculum-Grade ID: {$cg['id']}, Grade ID: {$cg['grade_id']}, Curriculum ID: {$cg['curriculum_id']}\n";
        if (isset($cg['grade'])) {
            echo "  Grade Details: {$cg['grade']['code']} - {$cg['grade']['name']}\n";
        }
    }
} else {
    echo "No curriculum-grade relationships found or empty response\n";
    echo "Raw response: " . $curriculumGradesResponse . "\n";
}

// Try to add a grade to curriculum if missing
echo "\nTrying to add grade ID 2 (K1) to curriculum 1...\n";
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['grade_id' => 2]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$addResponse = curl_exec($ch);
$addHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Add grade response (HTTP $addHttpCode): $addResponse\n";

// Check again after adding
echo "\nChecking curriculum-grade relationships again...\n";
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, null);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades');

$finalResponse = curl_exec($ch);
$finalGrades = json_decode($finalResponse, true);

if (is_array($finalGrades)) {
    foreach ($finalGrades as $cg) {
        echo "- Curriculum-Grade ID: {$cg['id']}, Grade ID: {$cg['grade_id']}\n";
    }
}

curl_close($ch);

?>
