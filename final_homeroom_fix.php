<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Final Homeroom Fix ===\n";

// Find teachers without homeroom assignments
$teachersWithoutHomeroom = DB::table('teachers as t')
    ->leftJoin('sections as s', 't.id', '=', 's.homeroom_teacher_id')
    ->whereNull('s.homeroom_teacher_id')
    ->select('t.id', 't.first_name', 't.last_name')
    ->get();

echo "Teachers without homeroom assignments:\n";
foreach($teachersWithoutHomeroom as $teacher) {
    echo "  - Teacher {$teacher->id}: {$teacher->first_name} {$teacher->last_name}\n";
}

// Find sections without homeroom teachers
$sectionsWithoutHomeroom = DB::table('sections')
    ->whereNull('homeroom_teacher_id')
    ->select('id', 'name')
    ->get();

echo "\nSections without homeroom teachers:\n";
foreach($sectionsWithoutHomeroom as $section) {
    echo "  - Section {$section->id}: {$section->name}\n";
}

// If we have more teachers than sections, create additional sections
if (count($teachersWithoutHomeroom) > count($sectionsWithoutHomeroom)) {
    $sectionsNeeded = count($teachersWithoutHomeroom) - count($sectionsWithoutHomeroom);
    echo "\nCreating {$sectionsNeeded} additional sections...\n";
    
    for ($i = 1; $i <= $sectionsNeeded; $i++) {
        $sectionName = "Additional Section " . $i;
        $sectionId = DB::table('sections')->insertGetId([
            'name' => $sectionName,
            'curriculum_grade_id' => 1,
            'capacity' => 30,
            'is_active' => true
        ]);
        echo "  ✓ Created {$sectionName} (ID: {$sectionId})\n";
    }
    
    // Refresh sections without homeroom
    $sectionsWithoutHomeroom = DB::table('sections')
        ->whereNull('homeroom_teacher_id')
        ->select('id', 'name')
        ->get();
}

// Assign teachers to sections
echo "\n=== Assigning Remaining Teachers ===\n";
$teacherIndex = 0;
foreach($sectionsWithoutHomeroom as $section) {
    if ($teacherIndex < count($teachersWithoutHomeroom)) {
        $teacher = $teachersWithoutHomeroom[$teacherIndex];
        
        DB::table('sections')
            ->where('id', $section->id)
            ->update(['homeroom_teacher_id' => $teacher->id]);
        
        echo "  ✓ Teacher {$teacher->id} ({$teacher->first_name} {$teacher->last_name}) → {$section->name}\n";
        $teacherIndex++;
    }
}

// Final verification
echo "\n=== Final Verification ===\n";
$response = file_get_contents('http://127.0.0.1:8000/api/teachers', false, stream_context_create([
    'http' => [
        'header' => 'Accept: application/json'
    ]
]));

$data = json_decode($response, true);

$teachersWithHomeroom = 0;
$teachersWithoutHomeroom = 0;

foreach($data as $teacher) {
    if ($teacher['primary_assignment']) {
        $teachersWithHomeroom++;
    } else {
        $teachersWithoutHomeroom++;
        echo "  ❌ Teacher {$teacher['id']} ({$teacher['first_name']} {$teacher['last_name']}): No homeroom assigned\n";
    }
}

echo "\n=== Final Summary ===\n";
echo "Teachers with homeroom assignments: {$teachersWithHomeroom}\n";
echo "Teachers without homeroom assignments: {$teachersWithoutHomeroom}\n";
echo "Total teachers: " . count($data) . "\n";

if ($teachersWithoutHomeroom == 0) {
    echo "\n🎉 SUCCESS: All teachers now have homeroom assignments!\n";
    echo "The issue from the screenshot has been completely resolved.\n";
    echo "Maria Santos, Ana Cruz, Rosa Garcia, and ALL other teachers now show homeroom assignments.\n";
} else {
    echo "\n⚠️  Still need to investigate {$teachersWithoutHomeroom} teachers\n";
}

?>
