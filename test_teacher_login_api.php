<?php
// Test teacher login API
echo "=== TESTING TEACHER LOGIN API ===\n\n";

// Test login with Maria Santos credentials
$loginData = [
    'username' => 'maria.santos',
    'password' => 'teacher123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/teacher/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login attempt with username: {$loginData['username']}\n";
echo "HTTP Status: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Login successful!\n";
    echo "Teacher: {$data['data']['teacher']['full_name']}\n";
    echo "Assignments: " . count($data['data']['assignments']) . "\n";
    
    foreach ($data['data']['assignments'] as $assignment) {
        echo "  - {$assignment->section_name}: {$assignment->subject_name} ({$assignment->role})\n";
    }
    
    $token = $data['data']['token'];
    echo "\nToken: $token\n";
    
    // Test profile endpoint
    echo "\n=== TESTING PROFILE ENDPOINT ===\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/teacher/profile');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $profileResponse = curl_exec($ch);
    $profileCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Profile HTTP Status: $profileCode\n";
    if ($profileCode === 200) {
        echo "✅ Profile endpoint working\n";
    } else {
        echo "❌ Profile endpoint failed\n";
        echo "Response: $profileResponse\n";
    }
    
} else {
    echo "❌ Login failed\n";
    echo "Response: $response\n";
}
?>
