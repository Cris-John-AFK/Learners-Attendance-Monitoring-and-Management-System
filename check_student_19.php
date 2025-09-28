<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING STUDENT ID 19 ===\n";
    $stmt = $pdo->query('SELECT id, "firstName", "lastName", "studentId" FROM students WHERE id = 19');
    if ($stmt->rowCount() > 0) {
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Student ID 19 exists:\n";
        echo "  Name: " . $student['firstName'] . " " . $student['lastName'] . "\n";
        echo "  Student ID: " . $student['studentId'] . "\n";
    } else {
        echo "❌ Student ID 19 does NOT exist\n";
    }
    
    echo "\n=== CHECKING QR CODE FOR STUDENT 19 ===\n";
    $stmt = $pdo->query('SELECT * FROM student_qr_codes WHERE student_id = 19');
    if ($stmt->rowCount() > 0) {
        echo "✅ QR code exists for student 19:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  QR: " . $row['qr_code_data'] . "\n";
            echo "  Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "❌ No QR code found for student 19\n";
        
        // Check what students DO have QR codes
        echo "\n=== STUDENTS WITH QR CODES ===\n";
        $stmt = $pdo->query('SELECT DISTINCT student_id FROM student_qr_codes WHERE is_active = true');
        $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Students with active QR codes: " . implode(', ', $studentIds) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
