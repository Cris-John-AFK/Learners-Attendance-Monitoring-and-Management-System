<?php

// Test QR code validation API
$url = 'http://127.0.0.1:8000/api/qr-codes/validate';
$data = json_encode(['qr_code_data' => 'STUDENT_4_QR_2024']);

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => $data
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "Error making request\n";
} else {
    echo "Response:\n";
    echo $result . "\n";
    
    $response = json_decode($result, true);
    if ($response && isset($response['valid']) && $response['valid']) {
        echo "\nQR Code is valid!\n";
        echo "Student: " . $response['student']['firstName'] . " " . $response['student']['lastName'] . "\n";
    } else {
        echo "\nQR Code is invalid or not found\n";
    }
}

?>
