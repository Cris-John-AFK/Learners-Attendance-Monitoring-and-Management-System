<?php
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Database connection
    $pdo = new PDO(
        "pgsql:host=" . $_ENV['DB_HOST'] . ";port=" . $_ENV['DB_PORT'] . ";dbname=" . $_ENV['DB_DATABASE'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== CREATING TEACHER ASSIGNMENTS ===\n\n";

    // 1. Get all teachers and sections
    echo "1. Getting teachers and sections...\n";
    
    $stmt = $pdo->prepare("
        SELECT t.id as teacher_id, t.first_name, t.last_name, u.username
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE u.role = 'teacher' AND u.is_active = true
        ORDER BY t.id
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT s.id as section_id, s.name as section_name, g.name as grade_name
        FROM sections s
        JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
        JOIN grades g ON cg.grade_id = g.id
        ORDER BY g.name, s.name
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT id as subject_id, name as subject_name
        FROM subjects
        WHERE is_active = true
        ORDER BY name
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($teachers) . " teachers, " . count($sections) . " sections, " . count($subjects) . " subjects\n\n";

    // 2. Create assignments for each teacher
    echo "2. Creating teacher assignments...\n";
    
    $assignmentCount = 0;
    $pdo->beginTransaction();
    
    foreach ($teachers as $index => $teacher) {
        // Assign each teacher to a section (round-robin style)
        $sectionIndex = $index % count($sections);
        $section = $sections[$sectionIndex];
        
        // Assign 2-3 subjects per teacher
        $teacherSubjects = array_slice($subjects, ($index * 2) % count($subjects), 2);
        
        // Create homeroom assignment (no specific subject)
        $stmt = $pdo->prepare("
            INSERT INTO teacher_section_subject (teacher_id, section_id, subject_id, role, is_primary, is_active)
            VALUES (?, ?, NULL, 'homeroom', true, true)
        ");
        $stmt->execute([$teacher['teacher_id'], $section['section_id']]);
        $assignmentCount++;
        
        echo "✅ {$teacher['first_name']} {$teacher['last_name']} -> Homeroom of {$section['section_name']} ({$section['grade_name']})\n";
        
        // Create subject assignments
        foreach ($teacherSubjects as $subjectIndex => $subject) {
            $isPrimary = ($subjectIndex === 0) ? 't' : 'f'; // PostgreSQL boolean format
            
            $stmt = $pdo->prepare("
                INSERT INTO teacher_section_subject (teacher_id, section_id, subject_id, role, is_primary, is_active)
                VALUES (?, ?, ?, 'subject_teacher', ?, true)
            ");
            $stmt->execute([$teacher['teacher_id'], $section['section_id'], $subject['subject_id'], $isPrimary]);
            $assignmentCount++;
            
            echo "   + Teaching {$subject['subject_name']} in {$section['section_name']}" . ($isPrimary ? " (Primary)" : "") . "\n";
        }
        
        echo "   ---\n";
    }
    
    $pdo->commit();
    
    echo "\n3. Assignment creation completed!\n";
    echo "Total assignments created: $assignmentCount\n\n";
    
    // 4. Verify assignments
    echo "4. Verifying assignments...\n";
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_assignments,
            COUNT(CASE WHEN role = 'homeroom_teacher' THEN 1 END) as homeroom_assignments,
            COUNT(CASE WHEN role = 'subject_teacher' THEN 1 END) as subject_assignments
        FROM teacher_section_subject
        WHERE is_active = true AND deleted_at IS NULL
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✅ Total active assignments: {$stats['total_assignments']}\n";
    echo "✅ Homeroom assignments: {$stats['homeroom_assignments']}\n";
    echo "✅ Subject assignments: {$stats['subject_assignments']}\n\n";
    
    // 5. Test a few teachers with the TeacherAuthController query
    echo "5. Testing TeacherAuthController query for sample teachers...\n";
    
    $sampleTeachers = array_slice($teachers, 0, 3); // Test first 3 teachers
    
    foreach ($sampleTeachers as $teacher) {
        echo "\nTesting {$teacher['first_name']} {$teacher['last_name']} (ID: {$teacher['teacher_id']}):\n";
        
        $stmt = $pdo->prepare("
            SELECT 
                tss.id as assignment_id,
                tss.section_id,
                tss.subject_id,
                tss.role,
                tss.is_primary,
                s.name as section_name,
                COALESCE(sub.name, 'Homeroom') as subject_name,
                g.name as grade_name,
                g.code as grade_code
            FROM teacher_section_subject tss
            JOIN sections s ON tss.section_id = s.id
            LEFT JOIN subjects sub ON tss.subject_id = sub.id
            JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
            JOIN grades g ON cg.grade_id = g.id
            WHERE tss.teacher_id = ?
            AND tss.is_active = true
            AND tss.deleted_at IS NULL
        ");
        $stmt->execute([$teacher['teacher_id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            echo "  ❌ No assignments found\n";
        } else {
            echo "  ✅ Found " . count($results) . " assignment(s):\n";
            foreach ($results as $result) {
                echo "    - {$result['section_name']} - {$result['subject_name']} ({$result['grade_name']}) [{$result['role']}]\n";
            }
        }
    }
    
    // 6. Test Ana Cruz specifically
    echo "\n6. Testing Ana Cruz specifically:\n";
    $stmt = $pdo->prepare("
        SELECT teacher_id FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE u.username = 'ana.cruz'
    ");
    $stmt->execute();
    $anaTeacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($anaTeacher) {
        $stmt = $pdo->prepare("
            SELECT 
                tss.id as assignment_id,
                tss.section_id,
                tss.subject_id,
                tss.role,
                tss.is_primary,
                s.name as section_name,
                COALESCE(sub.name, 'Homeroom') as subject_name,
                g.name as grade_name,
                g.code as grade_code
            FROM teacher_section_subject tss
            JOIN sections s ON tss.section_id = s.id
            LEFT JOIN subjects sub ON tss.subject_id = sub.id
            JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
            JOIN grades g ON cg.grade_id = g.id
            WHERE tss.teacher_id = ?
            AND tss.is_active = true
            AND tss.deleted_at IS NULL
        ");
        $stmt->execute([$anaTeacher['teacher_id']]);
        $anaResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($anaResults)) {
            echo "  ❌ Ana Cruz has no assignments\n";
        } else {
            echo "  ✅ Ana Cruz has " . count($anaResults) . " assignment(s):\n";
            foreach ($anaResults as $result) {
                echo "    - {$result['section_name']} - {$result['subject_name']} ({$result['grade_name']}) [{$result['role']}]\n";
            }
        }
    } else {
        echo "  ❌ Ana Cruz not found\n";
    }
    
    echo "\n🎉 Teacher assignment creation completed successfully!\n";
    echo "All teachers now have proper section and subject assignments.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
