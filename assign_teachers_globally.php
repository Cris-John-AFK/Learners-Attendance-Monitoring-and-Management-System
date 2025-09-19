<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Create Global Teacher Assignments ===\n";

// Get available sections and subjects
$sections = DB::table('sections')->select('id', 'name')->get();
$subjects = DB::table('subjects')->select('id', 'name')->get();

echo "Available sections:\n";
foreach($sections as $section) {
    echo "  - Section {$section->id}: {$section->name}\n";
}

echo "\nAvailable subjects:\n";
foreach($subjects as $subject) {
    echo "  - Subject {$subject->id}: {$subject->name}\n";
}

// Create more sections if needed
echo "\n=== Creating Additional Sections ===\n";
$additionalSections = [
    ['name' => 'Grade 1 - Section A', 'curriculum_grade_id' => 1],
    ['name' => 'Grade 1 - Section B', 'curriculum_grade_id' => 1],
    ['name' => 'Grade 2 - Section A', 'curriculum_grade_id' => 1],
    ['name' => 'Grade 3 - Section A', 'curriculum_grade_id' => 1],
];

foreach($additionalSections as $sectionData) {
    $exists = DB::table('sections')->where('name', $sectionData['name'])->exists();
    if (!$exists) {
        $sectionId = DB::table('sections')->insertGetId([
            'name' => $sectionData['name'],
            'curriculum_grade_id' => $sectionData['curriculum_grade_id'],
            'capacity' => 30,
            'is_active' => true
        ]);
        echo "  ✓ Created section: {$sectionData['name']} (ID: {$sectionId})\n";
    } else {
        echo "  - Section already exists: {$sectionData['name']}\n";
    }
}

// Get updated sections list
$allSections = DB::table('sections')->select('id', 'name')->get();

// Assign teachers to sections as homeroom teachers
echo "\n=== Assigning Teachers as Homeroom Teachers ===\n";

$teachers = DB::table('teachers')
    ->select('id', 'first_name', 'last_name')
    ->orderBy('id')
    ->get();

$sectionIndex = 0;
foreach($teachers as $teacher) {
    if ($sectionIndex < count($allSections)) {
        $section = $allSections[$sectionIndex];
        
        // Check if section already has a homeroom teacher
        $currentHomeroom = DB::table('sections')
            ->where('id', $section->id)
            ->value('homeroom_teacher_id');
        
        if (!$currentHomeroom) {
            // Assign this teacher as homeroom teacher
            DB::table('sections')
                ->where('id', $section->id)
                ->update(['homeroom_teacher_id' => $teacher->id]);
            
            echo "  ✓ Assigned Teacher {$teacher->id} ({$teacher->first_name} {$teacher->last_name}) as homeroom for {$section->name}\n";
            
            // Also create a teacher-section-subject assignment for homeroom
            $existingAssignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacher->id)
                ->where('section_id', $section->id)
                ->whereNull('subject_id')
                ->exists();
            
            if (!$existingAssignment) {
                DB::table('teacher_section_subject')->insert([
                    'teacher_id' => $teacher->id,
                    'section_id' => $section->id,
                    'subject_id' => null, // Homeroom has no specific subject
                    'role' => 'homeroom_teacher',
                    'is_primary' => true,
                    'is_active' => true
                ]);
                echo "    ✓ Created homeroom assignment record\n";
            }
        } else {
            echo "  - Section {$section->name} already has homeroom teacher ID {$currentHomeroom}\n";
        }
        
        $sectionIndex++;
    }
}

// Test the API to see if homeroom assignments are now visible
echo "\n=== Testing API Response ===\n";
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

echo "\n✅ Global teacher assignment completed!\n";

?>
