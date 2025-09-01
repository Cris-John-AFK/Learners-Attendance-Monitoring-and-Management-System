<?php

echo "Testing API endpoints...\n\n";

// Test sections API
echo "Testing sections API: GET /api/sections/grade/1\n";
$sectionsUrl = 'http://localhost:8000/api/sections/grade/1?curriculum_id=1';
$sectionsResponse = file_get_contents($sectionsUrl);

if ($sectionsResponse === false) {
    echo "❌ Sections API failed\n";
} else {
    $sectionsData = json_decode($sectionsResponse, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Sections API working - Found " . count($sectionsData) . " sections\n";
        foreach ($sectionsData as $section) {
            echo "  - Section: {$section['name']} (ID: {$section['id']})\n";
        }
    } else {
        echo "❌ Sections API returned invalid JSON\n";
        echo "Response: " . substr($sectionsResponse, 0, 200) . "...\n";
    }
}

echo "\n";

// Test teachers API
echo "Testing teachers API: GET /api/teachers\n";
$teachersUrl = 'http://localhost:8000/api/teachers';
$teachersResponse = file_get_contents($teachersUrl);

if ($teachersResponse === false) {
    echo "❌ Teachers API failed\n";
} else {
    $teachersData = json_decode($teachersResponse, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Teachers API working - Found " . count($teachersData) . " teachers\n";
        foreach ($teachersData as $teacher) {
            echo "  - Teacher: {$teacher['first_name']} {$teacher['last_name']} (ID: {$teacher['id']})\n";
        }
    } else {
        echo "❌ Teachers API returned invalid JSON\n";
        echo "Response: " . substr($teachersResponse, 0, 200) . "...\n";
    }
}

echo "\n";

// Test grades API
echo "Testing grades API: GET /api/grades\n";
$gradesUrl = 'http://localhost:8000/api/grades';
$gradesResponse = file_get_contents($gradesUrl);

if ($gradesResponse === false) {
    echo "❌ Grades API failed\n";
} else {
    $gradesData = json_decode($gradesResponse, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Grades API working - Found " . count($gradesData) . " grades\n";
        foreach ($gradesData as $grade) {
            echo "  - Grade: {$grade['name']} (Code: {$grade['code']})\n";
        }
    } else {
        echo "❌ Grades API returned invalid JSON\n";
        echo "Response: " . substr($gradesResponse, 0, 200) . "...\n";
    }
}

echo "\nAPI testing complete.\n";
