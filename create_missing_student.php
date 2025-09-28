<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CREATING MISSING STUDENT RECORD ===\n";
    
    // Check if student 19 already exists
    $stmt = $pdo->query('SELECT id FROM students WHERE id = 19');
    if ($stmt->rowCount() > 0) {
        echo "✅ Student ID 19 already exists\n";
    } else {
        echo "Creating student record for ID 19...\n";
        
        // Insert student record
        $insertSQL = '
            INSERT INTO students (
                id, "firstName", "lastName", "studentId", "gradeLevel", 
                section, gender, "profilePhoto", status, 
                created_at, updated_at
            ) VALUES (
                19, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                NOW(), NOW()
            )
        ';
        
        $stmt = $pdo->prepare($insertSQL);
        $stmt->execute([
            'Test Student',           // firstName
            'Nineteen',              // lastName  
            'STU-019',               // studentId
            'Kinder One',            // gradeLevel
            'Kinder One',            // section
            'male',                  // gender
            '/demo/images/avatar/default-male-student.png', // profilePhoto
            'active'                 // status
        ]);
        
        echo "✅ Student record created successfully\n";
    }
    
    // Verify the student now exists and can be joined with QR code
    echo "\n=== VERIFYING STUDENT-QR JOIN ===\n";
    $stmt = $pdo->query('
        SELECT 
            s.id, s."firstName", s."lastName", s."gradeLevel", s.section,
            qr.qr_code_data, qr.is_active
        FROM students s
        JOIN student_qr_codes qr ON s.id = qr.student_id
        WHERE s.id = 19 AND qr.is_active = true
    ');
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Student-QR join successful:\n";
        echo "  Name: " . $result['firstName'] . " " . $result['lastName'] . "\n";
        echo "  Grade: " . $result['gradeLevel'] . "\n";
        echo "  Section: " . $result['section'] . "\n";
        echo "  QR Code: " . substr($result['qr_code_data'], 0, 30) . "...\n";
        echo "  Active: " . ($result['is_active'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Student-QR join failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
