<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking homeroom teacher assignments...\n\n";

// Check Sampaguita
$sampaguita = DB::table('sections')->where('name', 'Sampaguita')->first();
echo "=== SAMPAGUITA ===\n";
echo "Section ID: {$sampaguita->id}\n";
echo "Homeroom Teacher ID: " . ($sampaguita->homeroom_teacher_id ?? 'NULL') . "\n";

if ($sampaguita->homeroom_teacher_id) {
    $teacher = DB::table('teachers')->where('id', $sampaguita->homeroom_teacher_id)->first();
    echo "Teacher: {$teacher->first_name} {$teacher->last_name}\n";
} else {
    echo "No homeroom teacher assigned!\n";
}

echo "\n=== MABINI ===\n";
$mabini = DB::table('sections')->where('name', 'Mabini')->first();
if ($mabini) {
    echo "Section ID: {$mabini->id}\n";
    echo "Homeroom Teacher ID: " . ($mabini->homeroom_teacher_id ?? 'NULL') . "\n";
    
    if ($mabini->homeroom_teacher_id) {
        $teacher = DB::table('teachers')->where('id', $mabini->homeroom_teacher_id)->first();
        echo "Teacher: {$teacher->first_name} {$teacher->last_name}\n";
    } else {
        echo "No homeroom teacher assigned!\n";
    }
} else {
    echo "Section not found!\n";
}

echo "\n=== ALL SECTIONS WITH HOMEROOM TEACHERS ===\n";
$sectionsWithHomeroom = DB::table('sections as s')
    ->join('teachers as t', 's.homeroom_teacher_id', '=', 't.id')
    ->select('s.name as section_name', 't.first_name', 't.last_name', 's.homeroom_teacher_id')
    ->get();

foreach ($sectionsWithHomeroom as $s) {
    echo "{$s->section_name} => {$s->first_name} {$s->last_name} (ID: {$s->homeroom_teacher_id})\n";
}

echo "\nDone!\n";
