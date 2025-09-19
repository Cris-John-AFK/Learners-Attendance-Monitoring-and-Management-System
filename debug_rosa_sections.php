<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Debug Rosa Garcia's Sections and Sessions ===\n";

$teacherId = 3; // Rosa Garcia

// Check Rosa's homeroom sections
echo "Rosa Garcia's homeroom sections:\n";
$homeroomSections = DB::table('sections')
    ->where('homeroom_teacher_id', $teacherId)
    ->select('id', 'name')
    ->get();

foreach($homeroomSections as $section) {
    echo "  - Section {$section->id}: {$section->name} (Homeroom)\n";
}

// Check Rosa's teaching assignments
echo "\nRosa Garcia's teaching assignments:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->where('tss.teacher_id', $teacherId)
    ->select('s.id as section_id', 's.name as section_name', 'sub.name as subject_name')
    ->get();

foreach($assignments as $assignment) {
    echo "  - Section {$assignment->section_id} ({$assignment->section_name}): {$assignment->subject_name}\n";
}

// Check attendance sessions for Rosa's sections
echo "\nAttendance sessions for Rosa's sections:\n";
$rosaSectionIds = $assignments->pluck('section_id')->unique()->toArray();

foreach($rosaSectionIds as $sectionId) {
    $sectionName = DB::table('sections')->where('id', $sectionId)->value('name');
    echo "\n  Section {$sectionId} ({$sectionName}) sessions:\n";
    
    $sessions = DB::table('attendance_sessions as as')
        ->leftJoin('teachers as t', 'as.teacher_id', '=', 't.id')
        ->leftJoin('subjects as sub', 'as.subject_id', '=', 'sub.id')
        ->where('as.section_id', $sectionId)
        ->select('as.*', 't.first_name', 't.last_name', 'sub.name as subject_name')
        ->orderBy('as.session_date', 'desc')
        ->get();
    
    if ($sessions->isEmpty()) {
        echo "    No sessions found\n";
    } else {
        foreach($sessions as $session) {
            echo "    - Session {$session->id}: {$session->session_date} - {$session->subject_name} (by {$session->first_name} {$session->last_name})\n";
        }
    }
}

// Test the API for Rosa's homeroom section (Kinder Two)
echo "\nTesting API for Kinder Two (Section 2) - Rosa's homeroom:\n";
try {
    $response = file_get_contents('http://127.0.0.1:8000/api/attendance-records/section/2?start_date=2025-09-01&end_date=2025-09-19', false, stream_context_create([
        'http' => [
            'header' => 'Accept: application/json'
        ]
    ]));
    
    $apiData = json_decode($response, true);
    if ($apiData && isset($apiData['sessions'])) {
        echo "  API returned " . count($apiData['sessions']) . " sessions for Kinder Two:\n";
        foreach($apiData['sessions'] as $session) {
            echo "    - Session {$session['id']}: {$session['session_date']} - {$session['subject']['name']}\n";
        }
    } else {
        echo "  API Error or no sessions\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

?>
