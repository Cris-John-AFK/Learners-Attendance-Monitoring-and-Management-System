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

    echo "=== UNIVERSAL TEACHER ASSIGNMENT DEBUG ===\n\n";

    // 1. Check all teacher users and their assignment status
    echo "1. All teacher users and assignment status:\n";
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username, 
            u.email, 
            u.is_active as user_active,
            t.id as teacher_id,
            t.first_name,
            t.last_name,
            COUNT(tss.id) as total_assignments,
            COUNT(CASE WHEN tss.is_active = true AND tss.deleted_at IS NULL THEN 1 END) as active_assignments
        FROM users u
        JOIN teachers t ON u.id = t.user_id
        LEFT JOIN teacher_section_subject tss ON t.id = tss.teacher_id
        WHERE u.role = 'teacher'
        GROUP BY u.id, u.username, u.email, u.is_active, t.id, t.first_name, t.last_name
        ORDER BY t.first_name, t.last_name
    ");
    $stmt->execute();
    $allTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allTeachers as $teacher) {
        $status = $teacher['active_assignments'] > 0 ? "✅ HAS ASSIGNMENTS" : "❌ NO ASSIGNMENTS";
        echo "- {$teacher['first_name']} {$teacher['last_name']} (Username: {$teacher['username']})\n";
        echo "  Teacher ID: {$teacher['teacher_id']}, User ID: {$teacher['user_id']}\n";
        echo "  Active: " . ($teacher['user_active'] ? 'Yes' : 'No') . "\n";
        echo "  Total Assignments: {$teacher['total_assignments']}, Active: {$teacher['active_assignments']} - {$status}\n";
        echo "  ---\n";
    }

    // 2. Check all assignment records and their status
    echo "\n2. All teacher assignment records:\n";
    $stmt = $pdo->prepare("
        SELECT 
            tss.id as assignment_id,
            t.first_name,
            t.last_name,
            s.name as section_name,
            COALESCE(sub.name, 'Homeroom') as subject_name,
            g.name as grade_name,
            tss.role,
            tss.is_active,
            tss.deleted_at,
            tss.created_at
        FROM teacher_section_subject tss
        JOIN teachers t ON tss.teacher_id = t.id
        JOIN sections s ON tss.section_id = s.id
        LEFT JOIN subjects sub ON tss.subject_id = sub.id
        JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
        JOIN grades g ON cg.grade_id = g.id
        ORDER BY tss.created_at DESC
    ");
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assignments)) {
        echo "❌ No assignment records found in database\n";
    } else {
        echo "Found " . count($assignments) . " assignment records:\n";
        foreach ($assignments as $assignment) {
            $status = $assignment['is_active'] ? 'ACTIVE' : 'INACTIVE';
            $deleted = $assignment['deleted_at'] ? 'DELETED' : 'NOT DELETED';
            echo "- {$assignment['first_name']} {$assignment['last_name']} -> {$assignment['section_name']} ({$assignment['grade_name']}) - {$assignment['subject_name']}\n";
            echo "  Status: {$status}, {$deleted}, Role: {$assignment['role']}\n";
            echo "  Created: {$assignment['created_at']}\n";
            echo "  ---\n";
        }
    }

    // 3. Test TeacherAuthController query for ALL teachers
    echo "\n3. Testing TeacherAuthController query for all teachers:\n";
    foreach ($allTeachers as $teacher) {
        if ($teacher['active_assignments'] > 0) {
            echo "\nTesting for {$teacher['first_name']} {$teacher['last_name']} (ID: {$teacher['teacher_id']}):\n";
            
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
            $controllerResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($controllerResults)) {
                echo "  ❌ TeacherAuthController query returns NO results (but teacher has {$teacher['active_assignments']} active assignments)\n";
                echo "  This indicates a query mismatch or data integrity issue\n";
            } else {
                echo "  ✅ TeacherAuthController query returns " . count($controllerResults) . " result(s):\n";
                foreach ($controllerResults as $result) {
                    echo "    - {$result['section_name']} - {$result['subject_name']} ({$result['grade_name']})\n";
                }
            }
        }
    }

    // 4. Check for common issues
    echo "\n4. Checking for common assignment issues:\n";
    
    // Check for assignments with invalid foreign keys
    $stmt = $pdo->prepare("
        SELECT 
            tss.id,
            tss.teacher_id,
            tss.section_id,
            tss.subject_id,
            CASE WHEN t.id IS NULL THEN 'INVALID TEACHER' ELSE 'OK' END as teacher_status,
            CASE WHEN s.id IS NULL THEN 'INVALID SECTION' ELSE 'OK' END as section_status
        FROM teacher_section_subject tss
        LEFT JOIN teachers t ON tss.teacher_id = t.id
        LEFT JOIN sections s ON tss.section_id = s.id
        WHERE t.id IS NULL OR s.id IS NULL
    ");
    $stmt->execute();
    $invalidAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($invalidAssignments)) {
        echo "❌ Found " . count($invalidAssignments) . " assignments with invalid foreign keys:\n";
        foreach ($invalidAssignments as $invalid) {
            echo "  Assignment ID: {$invalid['id']}, Teacher: {$invalid['teacher_status']}, Section: {$invalid['section_status']}\n";
        }
    } else {
        echo "✅ All assignments have valid foreign keys\n";
    }

    // Check for sections without curriculum_grade
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.curriculum_grade_id
        FROM sections s
        LEFT JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
        WHERE cg.id IS NULL
    ");
    $stmt->execute();
    $invalidSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($invalidSections)) {
        echo "❌ Found " . count($invalidSections) . " sections with invalid curriculum_grade_id:\n";
        foreach ($invalidSections as $invalid) {
            echo "  Section ID: {$invalid['id']}, Name: {$invalid['name']}, Curriculum Grade ID: {$invalid['curriculum_grade_id']}\n";
        }
    } else {
        echo "✅ All sections have valid curriculum_grade references\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
