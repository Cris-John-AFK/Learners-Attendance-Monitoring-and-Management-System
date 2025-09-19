<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Teacher Section Subject Table Structure ===\n";

// Get table structure
$columns = DB::select("
    SELECT column_name, data_type, is_nullable, column_default 
    FROM information_schema.columns 
    WHERE table_name = 'teacher_section_subject' 
    ORDER BY ordinal_position
");

echo "Columns:\n";
foreach($columns as $column) {
    echo "  - {$column->column_name} ({$column->data_type}) - Nullable: {$column->is_nullable} - Default: {$column->column_default}\n";
}

// Get constraints
$constraints = DB::select("
    SELECT constraint_name, constraint_type 
    FROM information_schema.table_constraints 
    WHERE table_name = 'teacher_section_subject'
");

echo "\nConstraints:\n";
foreach($constraints as $constraint) {
    echo "  - {$constraint->constraint_name} ({$constraint->constraint_type})\n";
}

// Get check constraints details
$checkConstraints = DB::select("
    SELECT constraint_name, check_clause 
    FROM information_schema.check_constraints 
    WHERE constraint_name LIKE '%teacher_section_subject%'
");

echo "\nCheck Constraints:\n";
foreach($checkConstraints as $constraint) {
    echo "  - {$constraint->constraint_name}: {$constraint->check_clause}\n";
}

// Check current data
echo "\nCurrent teacher_section_subject data:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
    ->select('tss.*', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name')
    ->get();

foreach($assignments as $assignment) {
    echo "  - Teacher {$assignment->teacher_id} ({$assignment->first_name} {$assignment->last_name}): {$assignment->section_name} - " . ($assignment->subject_name ?: 'No Subject') . " (Role: {$assignment->role})\n";
}

?>
