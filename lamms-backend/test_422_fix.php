<?php

echo "=== TESTING FIXED 422 ERROR - NULL SUBJECT_ID ===\n\n";

// Test with null subject_id (homeroom attendance)
$url = 'http://localhost:8000/api/attendance';
$testData = [
    "section_id" => 13,
    "subject_id" => null,  // This should now work
    "teacher_id" => 1,
    "date" => "2025-09-04",
    "attendance" => [
        [
            "student_id" => 2,
            "attendance_status_id" => 1,
            "remarks" => "Present - homeroom"
        ]
    ]
];

echo "Testing homeroom attendance (null subject_id):\n";
echo "URL: {$url}\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: {$httpCode}\n";

if ($httpCode == 422) {
    echo "❌ Still getting 422 error:\n";
    $errorData = json_decode($response, true);
    print_r($errorData);
} elseif ($httpCode == 200) {
    echo "✅ SUCCESS! 422 error fixed!\n";
} else {
    echo "⚠️  Unexpected response: {$response}\n";
}

// Test with valid subject_id
echo "\n=== TESTING WITH VALID SUBJECT_ID ===\n";
$testData['subject_id'] = 1;
echo "Testing with subject_id = 1:\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: {$httpCode}\n";
echo $httpCode == 200 ? "✅ Subject attendance also working!\n" : "❌ Subject attendance failed\n";

echo "\n🎯 SUMMARY:\n";
echo "- Fixed validation to allow null subject_id for homeroom attendance\n";
echo "- Both homeroom (null subject) and subject-specific attendance should work\n";
echo "- This resolves the 422 'Unprocessable Content' error\n";