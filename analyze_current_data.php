<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "=== CURRENT DATABASE ANALYSIS ===\n\n";
    
    // Check existing sections
    echo "1. EXISTING SECTIONS:\n";
    $sections = DB::table('sections')->orderBy('name')->get();
    foreach ($sections as $section) {
        echo "   - ID: {$section->id}, Name: {$section->name}, Homeroom Teacher: {$section->homeroom_teacher_id}\n";
    }
    echo "\n";
    
    // Check existing teachers
    echo "2. EXISTING TEACHERS:\n";
    $teachers = DB::table('teachers')->orderBy('id')->get();
    foreach ($teachers as $teacher) {
        echo "   - ID: {$teacher->id}, Name: {$teacher->first_name} {$teacher->last_name}\n";
    }
    echo "\n";
    
    // Check existing subjects
    echo "3. EXISTING SUBJECTS:\n";
    $subjects = DB::table('subjects')->orderBy('name')->get();
    foreach ($subjects as $subject) {
        echo "   - ID: {$subject->id}, Name: {$subject->name}, Code: {$subject->code}\n";
    }
    echo "\n";
    
    // Check existing teacher assignments (if table exists)
    echo "4. EXISTING TEACHER ASSIGNMENTS:\n";
    try {
        $assignments = DB::table('teacher_assignments')->get();
        if (count($assignments) > 0) {
            foreach ($assignments as $assignment) {
                echo "   - Assignment ID: {$assignment->id}\n";
            }
        } else {
            echo "   - No teacher assignments found\n";
        }
    } catch (Exception $e) {
        echo "   - Teacher assignments table may not exist or be empty\n";
    }
    echo "\n";
    
    // Check students count per section
    echo "5. STUDENTS COUNT PER SECTION:\n";
    $studentCounts = DB::select("
        SELECT s.name as section_name, s.id as section_id, COUNT(st.id) as student_count
        FROM sections s
        LEFT JOIN students st ON CAST(s.id AS VARCHAR) = st.section
        GROUP BY s.id, s.name
        ORDER BY s.name
    ");
    foreach ($studentCounts as $count) {
        echo "   - {$count->section_name} (ID: {$count->section_id}): {$count->student_count} students\n";
    }
    echo "\n";
    
    // Check for conflicts
    echo "6. POTENTIAL CONFLICTS:\n";
    
    // Check for duplicate section names
    $duplicates = DB::table('sections')
        ->select('name', DB::raw('COUNT(*) as count'))
        ->groupBy('name')
        ->having('count', '>', 1)
        ->get();
    if (count($duplicates) > 0) {
        echo "   - DUPLICATE SECTION NAMES:\n";
        foreach ($duplicates as $dup) {
            echo "     * {$dup->name} appears {$dup->count} times\n";
        }
    } else {
        echo "   - No duplicate section names found\n";
    }
    
    // Check teachers without homeroom assignments
    $noHomeroom = DB::table('teachers')->whereNull('homeroom_section_id')->count();
    echo "   - Teachers without homeroom: $noHomeroom\n";
    
    // Check sections without homeroom teachers
    $noTeacher = DB::table('sections as s')
        ->leftJoin('teachers as t', 's.id', '=', 't.homeroom_section_id')
        ->whereNull('t.id')
        ->pluck('s.name');
    if (count($noTeacher) > 0) {
        echo "   - Sections without homeroom teachers: " . implode(', ', $noTeacher->toArray()) . "\n";
    } else {
        echo "   - All sections have homeroom teachers\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
