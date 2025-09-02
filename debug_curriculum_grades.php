<?php

require_once 'lamms-backend/vendor/autoload.php';

echo "Debugging curriculum-grade API response structure...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/curriculums/1/grades');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Status: $httpCode\n";
echo "Raw Response: $response\n\n";

$data = json_decode($response, true);
echo "Decoded Response Structure:\n";
print_r($data);

curl_close($ch);

?>
