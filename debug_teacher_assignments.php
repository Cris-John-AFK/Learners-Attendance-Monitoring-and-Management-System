<?php

require_once __DIR__ . '/lamms-backend/vendor/autoload.php';

// Load Laravel app
$app = require_once __DIR__ . '/lamms-backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== Debugging Teacher Assignments API Error ===\n\n";
    
    // Test database connection
    echo "1. Testing database connection...\n";
    $connection = DB::connection()->getPdo();
    echo "✓ Database connection successful\n\n";
    
    // Check if teacher_section_subject table exists
    echo "2. Checking if teacher_section_subject table exists...\n";
    $tableExists = DB::select("SELECT to_regclass('teacher_section_subject') as exists");
    if ($tableExists[0]->exists) {
        echo "✓ teacher_section_subject table exists\n\n";
    } else {
        echo "✗ teacher_section_subject table does not exist\n\n";
        exit(1);
    }
    
    // Check table structure
    echo "3. Checking table structure...\n";
    $columns = DB::select("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'teacher_section_subject'
        ORDER BY ordinal_position
    ");
    
    foreach ($columns as $column) {
        echo "   - {$column->column_name} ({$column->data_type}) " . 
             ($column->is_nullable === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "\n";
    
    // Check if teacher ID 3 exists
    echo "4. Checking if teacher ID 3 exists...\n";
    $teacher = DB::table('teachers')->where('id', 3)->first();
    if ($teacher) {
        echo "✓ Teacher ID 3 exists: {$teacher->first_name} {$teacher->last_name}\n\n";
    } else {
        echo "✗ Teacher ID 3 does not exist\n\n";
    }
    
    // Check assignments for teacher ID 3
    echo "5. Checking assignments for teacher ID 3...\n";
    $assignments = DB::table('teacher_section_subject as tss')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
        ->where('tss.teacher_id', 3)
        ->where('tss.is_active', true)
        ->select([
            'tss.id as assignment_id',
            'tss.section_id',
            'tss.subject_id',
            'tss.role',
            'tss.is_primary',
            's.name as section_name',
            'sub.name as subject_name',
            'sub.code as subject_code'
        ])
        ->get();
    
    echo "Found " . count($assignments) . " assignments:\n";
    foreach ($assignments as $assignment) {
        echo "   - Section: {$assignment->section_name} | Subject: {$assignment->subject_name} | Role: {$assignment->role}\n";
    }
    echo "\n";
    
    // Check all teacher assignments
    echo "6. Checking all teacher assignments in table...\n";
    $allAssignments = DB::table('teacher_section_subject')->get();
    echo "Total assignments in table: " . count($allAssignments) . "\n";
    
    if (count($allAssignments) > 0) {
        echo "Sample assignments:\n";
        foreach ($allAssignments->take(5) as $assignment) {
            echo "   - Teacher: {$assignment->teacher_id} | Section: {$assignment->section_id} | Subject: {$assignment->subject_id} | Active: " . 
                 ($assignment->is_active ? 'Yes' : 'No') . "\n";
        }
    }
    echo "\n";
    
    // Test the exact query from the controller
    echo "7. Testing exact controller query...\n";
    try {
        $controllerResult = DB::table('teacher_section_subject as tss')
            ->join('sections as s', 'tss.section_id', '=', 's.id')
            ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
            ->where('tss.teacher_id', 3)
            ->where('tss.is_active', true)
            ->select([
                'tss.id as assignment_id',
                'tss.section_id',
                'tss.subject_id',
                'tss.role',
                'tss.is_primary',
                's.name as section_name',
                'sub.name as subject_name',
                'sub.code as subject_code'
            ])
            ->get();
            
        echo "✓ Controller query executed successfully\n";
        echo "Result count: " . count($controllerResult) . "\n\n";
        
        // Group by section like the controller does
        $grouped = $controllerResult->groupBy('section_id')->map(function ($sectionAssignments) {
            $section = $sectionAssignments->first();
            return [
                'section_id' => $section->section_id,
                'section_name' => $section->section_name,
                'subjects' => $sectionAssignments->map(function ($assignment) {
                    return [
                        'subject_id' => $assignment->subject_id,
                        'subject_name' => $assignment->subject_name,
                        'subject_code' => $assignment->subject_code,
                        'role' => $assignment->role,
                        'is_primary' => $assignment->is_primary
                    ];
                })->values()
            ];
        })->values();
        
        echo "Grouped result:\n";
        echo json_encode($grouped, JSON_PRETTY_PRINT) . "\n\n";
        
    } catch (\Exception $e) {
        echo "✗ Controller query failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "=== Debug Complete ===\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
