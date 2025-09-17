<?php
// Check Ana Cruz's teacher assignments in the database

$host = 'localhost';
$dbname = 'lamms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Ana Cruz Teacher Data ===\n";
    
    // Find Ana Cruz user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'ana.cruz'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "User ID: {$user['id']}, Username: {$user['username']}\n";
        
        // Find teacher record
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $teacher = $stmt->fetch();
        
        if ($teacher) {
            echo "Teacher ID: {$teacher['id']}, Name: {$teacher['first_name']} {$teacher['last_name']}\n";
            
            echo "\n=== Teacher Assignments ===\n";
            
            // Check teacher_section_subject table
            $stmt = $pdo->prepare("
                SELECT tss.*, s.name as section_name, sub.name as subject_name, g.name as grade_name
                FROM teacher_section_subject tss
                LEFT JOIN sections s ON tss.section_id = s.id
                LEFT JOIN subjects sub ON tss.subject_id = sub.id
                LEFT JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
                LEFT JOIN grades g ON cg.grade_id = g.id
                WHERE tss.teacher_id = ?
            ");
            $stmt->execute([$teacher['id']]);
            $assignments = $stmt->fetchAll();
            
            if (count($assignments) > 0) {
                foreach ($assignments as $assignment) {
                    echo "- Section: {$assignment['section_name']}, Subject: {$assignment['subject_name']}, Grade: {$assignment['grade_name']}, Role: {$assignment['role']}, Active: " . ($assignment['is_active'] ? 'Yes' : 'No') . "\n";
                }
            } else {
                echo "No assignments found in teacher_section_subject table\n";
            }
            
            echo "\n=== All Available Sections ===\n";
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, g.name as grade_name
                FROM sections s
                LEFT JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
                LEFT JOIN grades g ON cg.grade_id = g.id
                ORDER BY g.display_order, s.name
            ");
            $stmt->execute();
            $sections = $stmt->fetchAll();
            
            foreach ($sections as $section) {
                echo "- Section ID: {$section['id']}, Name: {$section['name']}, Grade: {$section['grade_name']}\n";
            }
            
            echo "\n=== All Available Subjects ===\n";
            $stmt = $pdo->prepare("SELECT id, name, code FROM subjects ORDER BY name");
            $stmt->execute();
            $subjects = $stmt->fetchAll();
            
            foreach ($subjects as $subject) {
                echo "- Subject ID: {$subject['id']}, Name: {$subject['name']}, Code: {$subject['code']}\n";
            }
            
        } else {
            echo "No teacher record found for user ID: {$user['id']}\n";
        }
    } else {
        echo "User 'ana.cruz' not found\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
