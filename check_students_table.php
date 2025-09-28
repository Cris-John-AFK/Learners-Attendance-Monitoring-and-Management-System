<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== STUDENTS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'students' ORDER BY ordinal_position");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['column_name'] . ' (' . $row['data_type'] . ')' . "\n";
    }
    
    echo "\n=== SAMPLE STUDENT DATA ===\n";
    $stmt = $pdo->query('SELECT id, "firstName", "lastName", gender FROM students LIMIT 3');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . ", Name: " . $row['firstName'] . " " . $row['lastName'] . "\n";
        echo "Gender: " . ($row['gender'] ?? 'NULL') . "\n\n";
    }
    
    // Check if qr_codes table exists
    echo "=== CHECKING QR CODES TABLE ===\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name = 'qr_codes'");
    if ($stmt->rowCount() > 0) {
        echo "✅ qr_codes table exists\n";
        $stmt = $pdo->query('SELECT student_id, qr_code_data FROM qr_codes LIMIT 3');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "Student ID: " . $row['student_id'] . ", QR: " . substr($row['qr_code_data'], 0, 20) . "...\n";
        }
    } else {
        echo "❌ qr_codes table does not exist\n";
    }
    
    // Add default photos for students
    echo "=== UPDATING STUDENT PHOTOS ===\n";
    $updatePhotosSQL = '
        UPDATE students 
        SET "profilePhoto" = CASE 
            WHEN gender = \'male\' OR gender = \'M\' THEN \'/demo/images/avatar/default-male-student.png\'
            WHEN gender = \'female\' OR gender = \'F\' THEN \'/demo/images/avatar/default-female-student.png\'
            ELSE \'/demo/images/avatar/default-student.png\'
        END
        WHERE "profilePhoto" IS NULL OR "profilePhoto" = \'\'
    ';
    
    $affectedRows = $pdo->exec($updatePhotosSQL);
    echo "✅ Updated {$affectedRows} students with default photos\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
