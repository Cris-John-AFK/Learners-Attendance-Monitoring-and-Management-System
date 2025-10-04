<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Finding and removing duplicate sections...\n\n";

// Get all sections grouped by name and curriculum_grade_id
$allSections = DB::table('sections')
    ->select('id', 'name', 'curriculum_grade_id', 'homeroom_teacher_id', 'created_at')
    ->orderBy('created_at')
    ->get();

// Group by name + grade
$groups = [];
foreach ($allSections as $section) {
    $key = $section->name . '_' . $section->curriculum_grade_id;
    if (!isset($groups[$key])) {
        $groups[$key] = [];
    }
    $groups[$key][] = $section;
}

$totalDeleted = 0;

foreach ($groups as $key => $sections) {
    if (count($sections) > 1) {
        echo "Found " . count($sections) . " duplicates for: " . explode('_', $key)[0] . "\n";
        
        // Sort: prioritize sections with homeroom teacher, then oldest
        usort($sections, function($a, $b) {
            // If one has homeroom teacher and other doesn't, keep the one with teacher
            if ($a->homeroom_teacher_id && !$b->homeroom_teacher_id) return -1;
            if (!$a->homeroom_teacher_id && $b->homeroom_teacher_id) return 1;
            
            // Otherwise keep the oldest (first created)
            return strtotime($a->created_at) - strtotime($b->created_at);
        });
        
        // Keep the first one, delete the rest
        $keepSection = $sections[0];
        echo "  Keeping: ID {$keepSection->id} (Homeroom: " . ($keepSection->homeroom_teacher_id ?? 'NULL') . ")\n";
        
        for ($i = 1; $i < count($sections); $i++) {
            $deleteSection = $sections[$i];
            echo "  Deleting: ID {$deleteSection->id}\n";
            
            // First, reassign any students from duplicate section to the kept section
            DB::table('student_section')
                ->where('section_id', $deleteSection->id)
                ->update(['section_id' => $keepSection->id]);
            
            // Delete the duplicate section
            DB::table('sections')->where('id', $deleteSection->id)->delete();
            $totalDeleted++;
        }
        
        echo "\n";
    }
}

echo "=== SUMMARY ===\n";
echo "Total duplicate sections deleted: $totalDeleted\n";

// Show final section count
$finalCount = DB::table('sections')->count();
echo "Remaining sections: $finalCount\n";

echo "\nDone!\n";
