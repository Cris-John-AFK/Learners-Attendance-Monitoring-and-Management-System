<?php

echo "=== Debug Rosa's Dropdown Issue ===\n";

// Test the assignments API that the frontend calls
$response = file_get_contents('http://127.0.0.1:8000/api/teachers/3/assignments', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$data = json_decode($response, true);

echo "API Response for Rosa Garcia (Teacher 3):\n";
echo json_encode($data, JSON_PRETTY_PRINT);

echo "\n\nExpected Dropdown Options:\n";
if ($data['success'] && isset($data['assignments'])) {
    foreach($data['assignments'] as $assignment) {
        echo "- {$assignment['section_name']} (ID: {$assignment['section_id']})\n";
    }
} else {
    echo "No assignments found or API error\n";
}

echo "\n\nFrontend should show:\n";
echo "✅ Kinder One (where Rosa teaches Filipino)\n";
echo "✅ Kinder Two (Rosa's homeroom section)\n";

?>
