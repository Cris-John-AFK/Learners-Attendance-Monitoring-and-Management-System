<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Maria Santos Detailed ===\n";

$teacherId = 1; // Maria Santos

// Check Maria's assignments
echo "Maria Santos (Teacher 1) section assignments:\n";
$assignedSectionIds = DB::table('teacher_section_subject')
    ->where('teacher_id', $teacherId)
    ->where('is_active', true)
    ->pluck('section_id')
    ->unique()
    ->toArray();

echo "Assigned section IDs: " . implode(', ', $assignedSectionIds) . "\n";

// Check what sections these IDs correspond to
foreach($assignedSectionIds as $sectionId) {
    $section = DB::table('sections')->where('id', $sectionId)->first();
    echo "  Section {$sectionId}: {$section->name}\n";
}

// Test the current API call directly
echo "\nTesting API call for Maria Santos:\n";
$response = file_get_contents('http://127.0.0.1:8000/api/teachers/1/attendance-sessions', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$apiData = json_decode($response, true);
if ($apiData && isset($apiData['sessions'])) {
    echo "API returns " . count($apiData['sessions']) . " sessions:\n";
    foreach($apiData['sessions'] as $session) {
        echo "  Session {$session['id']}: {$session['section_name']} - {$session['subject_name']} - {$session['session_date']}\n";
    }
} else {
    echo "API Error: " . $response . "\n";
}

// Check if there are any inactive assignments that might be causing issues
echo "\nChecking ALL assignments (including inactive):\n";
$allAssignments = DB::table('teacher_section_subject as tss')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->where('tss.teacher_id', $teacherId)
    ->select('tss.section_id', 'tss.is_active', 's.name as section_name')
    ->get();

foreach($allAssignments as $assignment) {
    $status = $assignment->is_active ? 'ACTIVE' : 'INACTIVE';
    echo "  Section {$assignment->section_id} ({$assignment->section_name}): {$status}\n";
}

?>
