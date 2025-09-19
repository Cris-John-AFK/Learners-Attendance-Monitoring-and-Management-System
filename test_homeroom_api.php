<?php

$response = file_get_contents('http://127.0.0.1:8000/api/teachers', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$data = json_decode($response, true);

// Show first 3 teachers
foreach(array_slice($data, 0, 3) as $teacher) {
    echo "Teacher {$teacher['id']} ({$teacher['first_name']} {$teacher['last_name']}): ";
    if($teacher['primary_assignment']) {
        echo "{$teacher['primary_assignment']['subject']['name']} - {$teacher['primary_assignment']['section']['name']}";
    } else {
        echo "No homeroom assigned";
    }
    echo "\n";
    
    // Show subject assignments
    if (!empty($teacher['subject_assignments'])) {
        echo "  Subject assignments:\n";
        foreach($teacher['subject_assignments'] as $assignment) {
            echo "    - {$assignment['section']['name']}: {$assignment['subject']['name']}\n";
        }
    }
    echo "\n";
}

?>
