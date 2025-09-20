<?php
// Simple test to call SF2 endpoint directly
$url = 'http://127.0.0.1:8000/api/admin/reports/sf2/download/1/2025-09';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . $error . "\n";
echo "Response Length: " . strlen($response) . "\n";

if ($httpCode === 200) {
    echo "SUCCESS: SF2 endpoint is working!\n";
    // Save the file to verify it's valid Excel
    file_put_contents('test_sf2_output.xlsx', $response);
    echo "File saved as test_sf2_output.xlsx\n";
} else {
    echo "ERROR Response: " . substr($response, 0, 500) . "\n";
}
?>
