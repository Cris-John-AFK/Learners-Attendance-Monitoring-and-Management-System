<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "=== REAL DATABASE ANALYSIS ===\n\n";
    
    // Check student_details table
    echo "1. STUDENTS IN student_details TABLE:\n";
    $students = DB::table('student_details')->orderBy('id')->get();
    echo "   Total students: " . count($students) . "\n";
    
    // Sample students
    echo "   Sample students:\n";
    foreach ($students->take(10) as $student) {
        echo "   - ID: {$student->id}, Name: {$student->name}, Student ID: {$student->studentId}\n";
    }
    echo "\n";
    
    // Check teacher_section_subject table
    echo "2. TEACHER ASSIGNMENTS in teacher_section_subject TABLE:\n";
    $assignments = DB::table('teacher_section_subject as tss')
        ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
        ->select('tss.*', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name')
        ->orderBy('t.id')
        ->get();
    
    echo "   Total assignments: " . count($assignments) . "\n";
    echo "   Sample assignments:\n";
    foreach ($assignments->take(15) as $assignment) {
        $subjectName = $assignment->subject_name ?? 'No Subject';
        $role = $assignment->role ?? 'Unknown';
        $isPrimary = $assignment->is_primary ? 'PRIMARY' : 'SECONDARY';
        echo "   - {$assignment->first_name} {$assignment->last_name} -> {$assignment->section_name} ({$subjectName}) [{$role}, {$isPrimary}]\n";
    }
    echo "\n";
    
    // Check sections with students
    echo "3. SECTIONS WITH STUDENT COUNTS:\n";
    $sectionStudents = DB::table('sections as s')
        ->leftJoin('student_details as sd', function($join) {
            $join->whereRaw('CAST(s.id AS VARCHAR) = sd.section OR s.name = sd.section');
        })
        ->select('s.id', 's.name', DB::raw('COUNT(sd.id) as student_count'))
        ->groupBy('s.id', 's.name')
        ->orderBy('s.name')
        ->get();
    
    foreach ($sectionStudents as $section) {
        echo "   - {$section->name} (ID: {$section->id}): {$section->student_count} students\n";
    }
    echo "\n";
    
    // Check homeroom assignments
    echo "4. HOMEROOM TEACHER ASSIGNMENTS:\n";
    $homeroomAssignments = DB::table('teacher_section_subject as tss')
        ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->where('tss.role', 'homeroom')
        ->select('t.first_name', 't.last_name', 's.name as section_name', 'tss.is_primary')
        ->orderBy('s.name')
        ->get();
    
    foreach ($homeroomAssignments as $homeroom) {
        $primary = $homeroom->is_primary ? '[PRIMARY]' : '[SECONDARY]';
        echo "   - {$homeroom->first_name} {$homeroom->last_name} -> {$homeroom->section_name} {$primary}\n";
    }
    echo "\n";
    
    // Check subject specialists
    echo "5. SUBJECT SPECIALIST ASSIGNMENTS:\n";
    $specialists = DB::table('teacher_section_subject as tss')
        ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
        ->where('tss.role', '!=', 'homeroom')
        ->orWhereNull('tss.role')
        ->select('t.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name', 'tss.is_primary')
        ->orderBy('t.id')
        ->get();
    
    $teacherSubjects = [];
    foreach ($specialists as $spec) {
        $teacherName = "{$spec->first_name} {$spec->last_name}";
        if (!isset($teacherSubjects[$teacherName])) {
            $teacherSubjects[$teacherName] = [];
        }
        $teacherSubjects[$teacherName][] = "{$spec->section_name} ({$spec->subject_name})";
    }
    
    foreach ($teacherSubjects as $teacher => $subjects) {
        echo "   - {$teacher}:\n";
        foreach ($subjects as $subject) {
            echo "     * {$subject}\n";
        }
    }
    echo "\n";
    
    // Check what's missing
    echo "6. GAPS TO FILL:\n";
    
    // Sections without homeroom teachers
    $sectionsWithoutHomeroom = DB::table('sections as s')
        ->leftJoin('teacher_section_subject as tss', function($join) {
            $join->on('s.id', '=', 'tss.section_id')
                 ->where('tss.role', '=', 'homeroom');
        })
        ->whereNull('tss.id')
        ->pluck('s.name');
    
    if (count($sectionsWithoutHomeroom) > 0) {
        echo "   - Sections without homeroom teachers: " . implode(', ', $sectionsWithoutHomeroom->toArray()) . "\n";
    } else {
        echo "   - All sections have homeroom teachers ✅\n";
    }
    
    // Teachers without assignments
    $teachersWithoutAssignments = DB::table('teachers as t')
        ->leftJoin('teacher_section_subject as tss', 't.id', '=', 'tss.teacher_id')
        ->whereNull('tss.id')
        ->select('t.first_name', 't.last_name')
        ->get();
    
    if (count($teachersWithoutAssignments) > 0) {
        echo "   - Teachers without assignments:\n";
        foreach ($teachersWithoutAssignments as $teacher) {
            echo "     * {$teacher->first_name} {$teacher->last_name}\n";
        }
    } else {
        echo "   - All teachers have assignments ✅\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
