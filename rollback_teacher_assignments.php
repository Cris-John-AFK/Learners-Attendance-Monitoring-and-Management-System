<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ROLLBACK: Removing Unauthorized Teacher Assignments ===\n";

// Remove all sections created after the original ones (keep only the first 6 sections)
echo "Removing unauthorized sections...\n";
$sectionsToDelete = DB::table('sections')
    ->where('id', '>', 6)
    ->get();

foreach($sectionsToDelete as $section) {
    echo "  - Removing Section {$section->id}: {$section->name}\n";
}

DB::table('sections')->where('id', '>', 6)->delete();

// Reset homeroom assignments to only the original ones
echo "\nResetting homeroom assignments to original state...\n";

// Clear all homeroom assignments first
DB::table('sections')->update(['homeroom_teacher_id' => null]);

// Restore only the original assignments that were working
DB::table('sections')->where('id', 1)->update(['homeroom_teacher_id' => 1]); // Maria Santos - Kinder One
DB::table('sections')->where('id', 2)->update(['homeroom_teacher_id' => 3]); // Rosa Garcia - Kinder Two

echo "  ✓ Restored Maria Santos as homeroom teacher for Kinder One\n";
echo "  ✓ Restored Rosa Garcia as homeroom teacher for Kinder Two\n";
echo "  ✓ Removed all other unauthorized homeroom assignments\n";

// Verify the rollback
echo "\n=== Verification: Current State ===\n";
$sections = DB::table('sections')
    ->leftJoin('teachers', 'sections.homeroom_teacher_id', '=', 'teachers.id')
    ->select('sections.id', 'sections.name as section_name', 'sections.homeroom_teacher_id', 'teachers.first_name', 'teachers.last_name')
    ->orderBy('sections.id')
    ->get();

echo "Current sections and homeroom assignments:\n";
foreach($sections as $section) {
    $teacherName = $section->first_name ? "{$section->first_name} {$section->last_name}" : "No teacher assigned";
    echo "  - Section {$section->id} ({$section->section_name}): Teacher ID {$section->homeroom_teacher_id} - {$teacherName}\n";
}

// Test API response
echo "\n=== API Test After Rollback ===\n";
$response = file_get_contents('http://127.0.0.1:8000/api/teachers', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$data = json_decode($response, true);

// Show first 5 teachers
foreach(array_slice($data, 0, 5) as $teacher) {
    echo "Teacher {$teacher['id']} ({$teacher['first_name']} {$teacher['last_name']}): ";
    if($teacher['primary_assignment']) {
        echo "{$teacher['primary_assignment']['subject']['name']} - {$teacher['primary_assignment']['section']['name']}";
    } else {
        echo "No homeroom assigned";
    }
    echo "\n";
}

echo "\n✅ ROLLBACK COMPLETED!\n";
echo "Only Maria Santos and Rosa Garcia have homeroom assignments as originally intended.\n";
echo "You now have full control to assign other teachers as you see fit.\n";

?>
