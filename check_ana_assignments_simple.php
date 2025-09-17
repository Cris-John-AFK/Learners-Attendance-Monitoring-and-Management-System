<?php
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Database configuration
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'database' => 'lamms_db',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "=== Ana Cruz Teacher Data ===\n";
    
    // Find Ana Cruz user
    $user = Capsule::table('users')->where('username', 'ana.cruz')->first();
    
    if ($user) {
        echo "User ID: {$user->id}, Username: {$user->username}\n";
        
        // Find teacher record
        $teacher = Capsule::table('teachers')->where('user_id', $user->id)->first();
        
        if ($teacher) {
            echo "Teacher ID: {$teacher->id}, Name: {$teacher->first_name} {$teacher->last_name}\n";
            
            echo "\n=== Teacher Assignments ===\n";
            
            // Check teacher_section_subject table
            $assignments = Capsule::table('teacher_section_subject as tss')
                ->leftJoin('sections as s', 'tss.section_id', '=', 's.id')
                ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
                ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
                ->where('tss.teacher_id', $teacher->id)
                ->select('tss.*', 's.name as section_name', 'sub.name as subject_name', 'g.name as grade_name')
                ->get();
            
            if (count($assignments) > 0) {
                foreach ($assignments as $assignment) {
                    $active = $assignment->is_active ? 'Yes' : 'No';
                    echo "- Section: {$assignment->section_name}, Subject: {$assignment->subject_name}, Grade: {$assignment->grade_name}, Role: {$assignment->role}, Active: {$active}\n";
                }
            } else {
                echo "No assignments found in teacher_section_subject table\n";
            }
            
            echo "\n=== Available Sections (first 10) ===\n";
            $sections = Capsule::table('sections as s')
                ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
                ->select('s.id', 's.name', 'g.name as grade_name')
                ->limit(10)
                ->get();
            
            foreach ($sections as $section) {
                echo "- Section ID: {$section->id}, Name: {$section->name}, Grade: {$section->grade_name}\n";
            }
            
        } else {
            echo "No teacher record found for user ID: {$user->id}\n";
        }
    } else {
        echo "User 'ana.cruz' not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
