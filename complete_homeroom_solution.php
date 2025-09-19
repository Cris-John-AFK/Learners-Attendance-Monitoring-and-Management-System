<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Complete Global Homeroom Solution ===\n";

// Get all teachers
$allTeachers = DB::table('teachers')
    ->select('id', 'first_name', 'last_name')
    ->orderBy('id')
    ->get();

// Get all sections
$allSections = DB::table('sections')
    ->select('id', 'name', 'homeroom_teacher_id')
    ->orderBy('id')
    ->get();

echo "Total teachers: " . count($allTeachers) . "\n";
echo "Total sections: " . count($allSections) . "\n";

// Create additional sections if we have more teachers than sections
$sectionsNeeded = count($allTeachers) - count($allSections);
if ($sectionsNeeded > 0) {
    echo "\nCreating {$sectionsNeeded} additional sections...\n";
    
    for ($i = 1; $i <= $sectionsNeeded; $i++) {
        $sectionName = "Section " . (count($allSections) + $i);
        $sectionId = DB::table('sections')->insertGetId([
            'name' => $sectionName,
            'curriculum_grade_id' => 1, // Default curriculum grade
            'capacity' => 30,
            'is_active' => true
        ]);
        echo "  ✓ Created {$sectionName} (ID: {$sectionId})\n";
    }
    
    // Refresh sections list
    $allSections = DB::table('sections')
        ->select('id', 'name', 'homeroom_teacher_id')
        ->orderBy('id')
        ->get();
}

// Now assign each teacher to a section as homeroom teacher
echo "\n=== Assigning Homeroom Teachers ===\n";

$sectionIndex = 0;
foreach($allTeachers as $teacher) {
    if ($sectionIndex < count($allSections)) {
        $section = $allSections[$sectionIndex];
        
        // Check if section already has a homeroom teacher
        if (!$section->homeroom_teacher_id) {
            DB::table('sections')
                ->where('id', $section->id)
                ->update(['homeroom_teacher_id' => $teacher->id]);
            
            echo "  ✓ Teacher {$teacher->id} ({$teacher->first_name} {$teacher->last_name}) → {$section->name}\n";
        } else {
            echo "  - {$section->name} already has homeroom teacher ID {$section->homeroom_teacher_id}\n";
        }
        
        $sectionIndex++;
    }
}

// Test the complete API response
echo "\n=== Final API Test - All Teachers ===\n";
$response = file_get_contents('http://127.0.0.1:8000/api/teachers', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$data = json_decode($response, true);

$teachersWithHomeroom = 0;
$teachersWithoutHomeroom = 0;

foreach($data as $teacher) {
    $status = $teacher['primary_assignment'] ? 
        "{$teacher['primary_assignment']['subject']['name']} - {$teacher['primary_assignment']['section']['name']}" : 
        "No homeroom assigned";
    
    echo "Teacher {$teacher['id']} ({$teacher['first_name']} {$teacher['last_name']}): {$status}\n";
    
    if ($teacher['primary_assignment']) {
        $teachersWithHomeroom++;
    } else {
        $teachersWithoutHomeroom++;
    }
}

echo "\n=== Summary ===\n";
echo "Teachers with homeroom assignments: {$teachersWithHomeroom}\n";
echo "Teachers without homeroom assignments: {$teachersWithoutHomeroom}\n";
echo "Total teachers: " . count($data) . "\n";

if ($teachersWithoutHomeroom == 0) {
    echo "\n🎉 SUCCESS: All teachers now have homeroom assignments!\n";
} else {
    echo "\n⚠️  Still need to fix {$teachersWithoutHomeroom} teachers\n";
}

?>
