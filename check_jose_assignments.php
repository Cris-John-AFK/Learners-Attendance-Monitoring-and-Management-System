<?php
// Direct database connection
$host = '127.0.0.1';
$dbname = 'lamms_db';
$username = 'postgres';
$password = '';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Find Jose Ramos
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE name ILIKE ?");
    $stmt->execute(['%Jose Ramos%']);
    $jose = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$jose) {
        echo "Jose Ramos not found in teachers table\n";
        exit;
    }
    
    echo "=== JOSE RAMOS TEACHER INFO ===\n";
    echo "ID: {$jose->id}\n";
    echo "Name: {$jose->name}\n";
    echo "Employee ID: {$jose->employee_id}\n\n";
    
    // Check his current assignments
    echo "=== CURRENT ASSIGNMENTS ===\n";
    $stmt = $pdo->prepare("
        SELECT tss.*, s.name as section_name, s.grade_level, sub.name as subject_name, tss.is_homeroom_teacher
        FROM teacher_section_subject tss
        JOIN sections s ON tss.section_id = s.id
        JOIN subjects sub ON tss.subject_id = sub.id
        WHERE tss.teacher_id = ?
        ORDER BY s.grade_level, s.name
    ");
    $stmt->execute([$jose->id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    if (empty($assignments)) {
        echo "No assignments found for Jose Ramos\n";
    } else {
        foreach ($assignments as $assignment) {
            $homeroom = $assignment->is_homeroom_teacher ? ' (HOMEROOM)' : '';
            echo "Grade {$assignment->grade_level} - {$assignment->section_name}: {$assignment->subject_name}{$homeroom}\n";
        }
    }
    
    echo "\n=== ANALYSIS ===\n";
    
    // Check what grade levels he teaches
    $gradeLevels = array_unique(array_column($assignments, 'grade_level'));
    sort($gradeLevels);
    echo "Grade levels taught: " . implode(', ', $gradeLevels) . "\n";
    
    // Check if he's a homeroom teacher
    $homeroomAssignments = array_filter($assignments, function($a) { return $a->is_homeroom_teacher; });
    if (!empty($homeroomAssignments)) {
        echo "IS HOMEROOM TEACHER for:\n";
        foreach ($homeroomAssignments as $homeroom) {
            echo "  - Grade {$homeroom->grade_level} - {$homeroom->section_name}\n";
        }
    } else {
        echo "NOT a homeroom teacher (departmental/subject teacher)\n";
    }
    
    // Check teaching pattern
    $subjectTeaching = array_filter($assignments, function($a) { return !$a->is_homeroom_teacher; });
    if (!empty($subjectTeaching)) {
        echo "\nSubject teaching assignments:\n";
        foreach ($subjectTeaching as $subject) {
            echo "  - {$subject->subject_name} in Grade {$subject->grade_level} - {$subject->section_name}\n";
        }
    }
    
    // Determine teacher type based on assignments
    echo "\n=== TEACHER TYPE ANALYSIS ===\n";
    $k3Grades = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3'];
    $grade46Grades = ['Grade 4', 'Grade 5', 'Grade 6'];
    
    $teachesK3 = !empty(array_intersect($gradeLevels, $k3Grades));
    $teachesGrade46 = !empty(array_intersect($gradeLevels, $grade46Grades));
    
    if ($teachesK3 && $teachesGrade46) {
        echo "❌ VIOLATION: Teaching both K-3 AND Grade 4-6 (should not happen)\n";
    } elseif ($teachesK3 && !$teachesGrade46) {
        echo "✅ K-3 Teacher (can be homeroom for K-3 only)\n";
    } elseif (!$teachesK3 && $teachesGrade46) {
        echo "✅ Grade 4-6 Departmental Teacher (can be homeroom for Grade 4-6 only)\n";
    } else {
        echo "⚠️  Mixed or unclear teaching pattern\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
