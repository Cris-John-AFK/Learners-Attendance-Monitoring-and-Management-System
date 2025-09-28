<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING STUDENT_DETAILS TABLE ===\n";
    
    // Check if student_details table exists
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name = 'student_details'");
    if ($stmt->rowCount() > 0) {
        echo "✅ student_details table exists\n\n";
        
        // Get table structure
        echo "=== TABLE STRUCTURE ===\n";
        $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'student_details' ORDER BY ordinal_position");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['column_name'] . " (" . $row['data_type'] . ")\n";
        }
        
        // Check if student ID 19 exists in student_details
        echo "\n=== CHECKING STUDENT ID 19 ===\n";
        $stmt = $pdo->query('SELECT id, "firstName", "lastName", "gradeLevel", section, gender, "profilePhoto" FROM student_details WHERE id = 19');
        if ($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Student ID 19 exists in student_details:\n";
            echo "  Name: " . $student['firstName'] . " " . $student['lastName'] . "\n";
            echo "  Grade: " . $student['gradeLevel'] . "\n";
            echo "  Section: " . $student['section'] . "\n";
            echo "  Gender: " . $student['gender'] . "\n";
            echo "  Photo: " . $student['profilePhoto'] . "\n";
        } else {
            echo "❌ Student ID 19 does NOT exist in student_details\n";
        }
        
        // Test the JOIN with student_details and student_qr_codes
        echo "\n=== TESTING JOIN: student_details + student_qr_codes ===\n";
        $stmt = $pdo->query('
            SELECT 
                sd.id, sd."firstName", sd."lastName", sd."gradeLevel", sd.section, sd.gender, sd."profilePhoto",
                sqr.qr_code_data, sqr.is_active
            FROM student_details sd
            JOIN student_qr_codes sqr ON sd.id = sqr.student_id
            WHERE sd.id = 19 AND sqr.is_active = true
        ');
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ JOIN successful - Found student with QR code:\n";
            echo "  ID: " . $result['id'] . "\n";
            echo "  Name: " . $result['firstName'] . " " . $result['lastName'] . "\n";
            echo "  Grade: " . $result['gradeLevel'] . "\n";
            echo "  Section: " . $result['section'] . "\n";
            echo "  Gender: " . $result['gender'] . "\n";
            echo "  Photo: " . $result['profilePhoto'] . "\n";
            echo "  QR Code: " . $result['qr_code_data'] . "\n";
            echo "  Active: " . ($result['is_active'] ? 'Yes' : 'No') . "\n";
        } else {
            echo "❌ JOIN failed - No matching student found\n";
        }
        
        // Show all students in student_details
        echo "\n=== ALL STUDENTS IN STUDENT_DETAILS ===\n";
        $stmt = $pdo->query('SELECT id, "firstName", "lastName" FROM student_details ORDER BY id LIMIT 10');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID: " . $row['id'] . " - " . $row['firstName'] . " " . $row['lastName'] . "\n";
        }
        
    } else {
        echo "❌ student_details table does NOT exist\n";
        
        // Check what tables do exist
        echo "\n=== AVAILABLE TABLES ===\n";
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['table_name'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
