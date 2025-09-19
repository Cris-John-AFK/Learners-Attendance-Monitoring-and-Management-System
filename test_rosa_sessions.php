<?php

echo "=== Testing Rosa Garcia (Teacher 3) Sessions ===\n";

// Test Rosa Garcia's API endpoint
$response = file_get_contents('http://127.0.0.1:8000/api/teachers/3/attendance-sessions', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$apiData = json_decode($response, true);
if ($apiData && isset($apiData['sessions'])) {
    echo "Rosa Garcia should see sessions for Kinder One and Kinder Two (her assigned sections)\n";
    echo "API returns " . count($apiData['sessions']) . " sessions:\n";
    foreach($apiData['sessions'] as $session) {
        echo "  Session {$session['id']}: {$session['section_name']} - {$session['subject_name']} - {$session['session_date']}\n";
    }
} else {
    echo "API Error or no sessions returned\n";
    echo "Response: " . $response . "\n";
}

?>
