<?php
// Test the updated API endpoint
$url = 'http://localhost:8000/api/admin/attendance/analytics?date_range=current_year';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ API endpoint working (HTTP $httpCode)\n";
    echo "Response data:\n";
    echo json_encode($data, JSON_PRETTY_PRINT);
} else {
    echo "❌ API endpoint failed (HTTP $httpCode)\n";
    echo "Response: $response\n";
}
?>
