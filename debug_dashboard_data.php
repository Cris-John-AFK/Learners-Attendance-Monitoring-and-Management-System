<?php
try {
    $pdo = new PDO('pgsql:host=localhost;dbname=lamms_db', 'postgres', 'password');
    echo "=== CHECKING STUDENT DATA ===\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM student_details WHERE current_status = \'active\'');
    $result = $stmt->fetch();
    echo "Active students: " . $result['count'] . "\n";
    
    echo "\n=== CHECKING TEACHER ASSIGNMENTS ===\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM teacher_section_subject WHERE teacher_id = 3 AND is_active = true');
    $result = $stmt->fetch();
    echo "Teacher 3 assignments: " . $result['count'] . "\n";
    
    echo "\n=== CHECKING STUDENT-SECTION RELATIONSHIPS ===\n";
    $stmt = $pdo->query('SELECT ss.section_id, COUNT(*) as student_count FROM student_section ss WHERE ss.is_active = true GROUP BY ss.section_id ORDER BY ss.section_id');
    while ($row = $stmt->fetch()) {
        echo "Section " . $row['section_id'] . ": " . $row['student_count'] . " students\n";
    }
    
    echo "\n=== CHECKING ATTENDANCE RECORDS ===\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM attendances WHERE date >= CURRENT_DATE - INTERVAL \'30 days\'');
    $result = $stmt->fetch();
    echo "Recent attendance records: " . $result['count'] . "\n";
    
    echo "\n=== CHECKING SPECIFIC TEACHER 3 STUDENTS ===\n";
    $stmt = $pdo->query('
        SELECT DISTINCT sd.id, sd.name, ss.section_id, s.name as section_name
        FROM teacher_section_subject tss
        JOIN student_section ss ON tss.section_id = ss.section_id
        JOIN student_details sd ON ss.student_id = sd.id
        JOIN sections s ON ss.section_id = s.id
        WHERE tss.teacher_id = 3 AND tss.is_active = true AND ss.is_active = true
        ORDER BY sd.id
    ');
    while ($row = $stmt->fetch()) {
        echo "Student ID " . $row['id'] . ": " . $row['name'] . " (Section " . $row['section_id'] . " - " . $row['section_name'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
