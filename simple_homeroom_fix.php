<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Simple Homeroom Assignment Fix ===\n";

// Strategy: For every teacher who has subject assignments, make them homeroom teacher 
// for at least one of the sections they teach in

// Get all teachers with their section assignments
$teacherSections = DB::table('teacher_section_subject as tss')
    ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->select('t.id as teacher_id', 't.first_name', 't.last_name', 's.id as section_id', 's.name as section_name', 's.homeroom_teacher_id')
    ->distinct()
    ->orderBy('t.id')
    ->get();

// Group by teacher
$teacherData = [];
foreach($teacherSections as $assignment) {
    $teacherId = $assignment->teacher_id;
    if (!isset($teacherData[$teacherId])) {
        $teacherData[$teacherId] = [
            'name' => "{$assignment->first_name} {$assignment->last_name}",
            'sections' => []
        ];
    }
    
    $teacherData[$teacherId]['sections'][] = [
        'id' => $assignment->section_id,
        'name' => $assignment->section_name,
        'current_homeroom_teacher' => $assignment->homeroom_teacher_id
    ];
}

echo "Processing teachers with assignments:\n";

foreach($teacherData as $teacherId => $teacher) {
    echo "\nTeacher {$teacherId} ({$teacher['name']}):\n";
    
    // Check if this teacher is already a homeroom teacher for any section
    $isHomeroomTeacher = false;
    foreach($teacher['sections'] as $section) {
        if ($section['current_homeroom_teacher'] == $teacherId) {
            $isHomeroomTeacher = true;
            echo "  ✓ Already homeroom teacher for {$section['name']}\n";
            break;
        }
    }
    
    // If not a homeroom teacher yet, assign them to the first section they teach that doesn't have a homeroom teacher
    if (!$isHomeroomTeacher) {
        foreach($teacher['sections'] as $section) {
            if (!$section['current_homeroom_teacher']) {
                // Assign this teacher as homeroom teacher
                DB::table('sections')
                    ->where('id', $section['id'])
                    ->update(['homeroom_teacher_id' => $teacherId]);
                
                echo "  ✓ Assigned as homeroom teacher for {$section['name']}\n";
                break;
            }
        }
        
        // If all sections already have homeroom teachers, just report it
        if (!$isHomeroomTeacher) {
            $allHaveHomeroom = true;
            foreach($teacher['sections'] as $section) {
                if (!$section['current_homeroom_teacher']) {
                    $allHaveHomeroom = false;
                    break;
                }
            }
            if ($allHaveHomeroom) {
                echo "  - All sections this teacher teaches already have homeroom teachers\n";
            }
        }
    }
}

// Now assign remaining teachers (without any assignments) to sections without homeroom teachers
echo "\n=== Assigning remaining teachers ===\n";

$allTeachers = DB::table('teachers')->select('id', 'first_name', 'last_name')->get();
$teachersWithoutAssignments = [];

foreach($allTeachers as $teacher) {
    if (!isset($teacherData[$teacher->id])) {
        $teachersWithoutAssignments[] = $teacher;
    }
}

if (!empty($teachersWithoutAssignments)) {
    echo "Teachers without any subject assignments:\n";
    
    // Get sections without homeroom teachers
    $sectionsWithoutHomeroom = DB::table('sections')
        ->whereNull('homeroom_teacher_id')
        ->select('id', 'name')
        ->get();
    
    $teacherIndex = 0;
    foreach($sectionsWithoutHomeroom as $section) {
        if ($teacherIndex < count($teachersWithoutAssignments)) {
            $teacher = $teachersWithoutAssignments[$teacherIndex];
            
            DB::table('sections')
                ->where('id', $section->id)
                ->update(['homeroom_teacher_id' => $teacher->id]);
            
            echo "  ✓ Assigned Teacher {$teacher->id} ({$teacher->first_name} {$teacher->last_name}) as homeroom for {$section->name}\n";
            $teacherIndex++;
        }
    }
}

// Test the API to verify the fix
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

echo "\n✅ Homeroom assignment fix completed!\n";

?>
