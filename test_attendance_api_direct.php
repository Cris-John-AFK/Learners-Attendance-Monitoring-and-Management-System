<?php

// Direct test of the attendance records API endpoint
$url = 'http://127.0.0.1:8000/api/attendance-records/section/3?start_date=2025-09-01&end_date=2025-09-12';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

echo "Testing API endpoint: $url\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response:\n";
echo $response . "\n";

if ($error) {
    echo "cURL Error: $error\n";
}

if ($httpCode !== 200) {
    echo "\nAPI returned error. Checking Laravel logs...\n";
}
