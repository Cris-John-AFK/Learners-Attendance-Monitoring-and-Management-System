<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== FIXING GUARDHOUSE_ATTENDANCE TABLE ===\n";
    
    // Drop the existing foreign key constraint
    echo "1. Dropping existing foreign key constraint...\n";
    $dropConstraintSQL = "
        ALTER TABLE guardhouse_attendance 
        DROP CONSTRAINT IF EXISTS guardhouse_attendance_student_id_fkey
    ";
    $pdo->exec($dropConstraintSQL);
    echo "✅ Dropped existing constraint\n";
    
    // Add new foreign key constraint to student_details table
    echo "2. Adding new foreign key constraint to student_details...\n";
    $addConstraintSQL = "
        ALTER TABLE guardhouse_attendance 
        ADD CONSTRAINT guardhouse_attendance_student_id_fkey 
        FOREIGN KEY (student_id) REFERENCES student_details(id) ON DELETE CASCADE
    ";
    $pdo->exec($addConstraintSQL);
    echo "✅ Added new constraint to student_details table\n";
    
    // Test the insertion again
    echo "\n3. Testing attendance record insertion...\n";
    $insertSQL = "
        INSERT INTO guardhouse_attendance (
            student_id, qr_code_data, record_type, timestamp, date,
            guard_name, guard_id, is_manual, notes, created_at, updated_at
        ) VALUES (
            19, 'LAMMS_STUDENT_19_1758809765_oex5qkZo', 'check-in', NOW(), CURRENT_DATE,
            'Bread Doe', 'G-12345', false, null, NOW(), NOW()
        ) RETURNING id
    ";
    
    $stmt = $pdo->prepare($insertSQL);
    $stmt->execute();
    $insertedId = $stmt->fetchColumn();
    
    echo "✅ Successfully inserted attendance record with ID: $insertedId\n";
    
    // Test the join query
    echo "\n4. Testing join query...\n";
    $joinSQL = "
        SELECT 
            ga.*,
            sd.\"firstName\",
            sd.\"lastName\",
            sd.\"gradeLevel\",
            sd.section,
            sd.\"profilePhoto\",
            sd.gender
        FROM guardhouse_attendance ga
        JOIN student_details sd ON ga.student_id = sd.id
        WHERE ga.id = ?
    ";
    
    $stmt = $pdo->prepare($joinSQL);
    $stmt->execute([$insertedId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Join query successful:\n";
        echo "  Student: " . $result['firstName'] . " " . $result['lastName'] . "\n";
        echo "  Grade: " . $result['gradeLevel'] . "\n";
        echo "  Record Type: " . $result['record_type'] . "\n";
        echo "  Timestamp: " . $result['timestamp'] . "\n";
    } else {
        echo "❌ Join query failed\n";
    }
    
    // Clean up - delete the test record
    $pdo->prepare("DELETE FROM guardhouse_attendance WHERE id = ?")->execute([$insertedId]);
    echo "\n✅ Test record cleaned up\n";
    
    echo "\n=== GUARDHOUSE TABLE FIXED SUCCESSFULLY ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
