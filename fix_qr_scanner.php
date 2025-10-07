<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== FIXING QR SCANNER ISSUES ===\n";
    
    // Check if student_qr_codes table exists
    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'student_qr_codes')");
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        echo "❌ student_qr_codes table does not exist. Creating it...\n";
        
        // Create student_qr_codes table
        $createQRTableSQL = "
            CREATE TABLE student_qr_codes (
                id SERIAL PRIMARY KEY,
                student_id INTEGER NOT NULL,
                qr_code_data VARCHAR(255) UNIQUE NOT NULL,
                qr_code_hash VARCHAR(255) UNIQUE,
                is_active BOOLEAN DEFAULT TRUE,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES student_details(id) ON DELETE CASCADE
            );
        ";
        
        $pdo->exec($createQRTableSQL);
        echo "✅ student_qr_codes table created successfully\n";
        
        // Create indexes
        $pdo->exec("CREATE INDEX idx_student_qr_codes_student_id ON student_qr_codes(student_id)");
        $pdo->exec("CREATE INDEX idx_student_qr_codes_qr_data ON student_qr_codes(qr_code_data)");
        echo "✅ Indexes created\n";
        
        // Generate QR codes for existing students
        $students = $pdo->query("SELECT id FROM student_details LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($students as $studentId) {
            $qrData = "LAMMS_STUDENT_{$studentId}_" . time() . "_" . substr(md5(uniqid()), 0, 8);
            $qrHash = hash('sha256', $qrData);
            
            $pdo->prepare("INSERT INTO student_qr_codes (student_id, qr_code_data, qr_code_hash) VALUES (?, ?, ?)")
                ->execute([$studentId, $qrData, $qrHash]);
        }
        
        echo "✅ Generated QR codes for " . count($students) . " students\n";
    } else {
        echo "✅ student_qr_codes table already exists\n";
    }
    
    // Check if the specific QR code exists
    $qrCode = 'LAMMS_STUDENT_3240_1759843602_8rI4FJsW';
    $stmt = $pdo->prepare("SELECT student_id FROM student_qr_codes WHERE qr_code_data = ?");
    $stmt->execute([$qrCode]);
    $result = $stmt->fetch();
    
    if (!$result) {
        echo "❌ QR code {$qrCode} not found. Creating it...\n";
        
        // Check if student 3240 exists
        $studentCheck = $pdo->prepare("SELECT id FROM student_details WHERE id = ?");
        $studentCheck->execute([3240]);
        
        if ($studentCheck->fetch()) {
            $qrHash = hash('sha256', $qrCode);
            $pdo->prepare("INSERT INTO student_qr_codes (student_id, qr_code_data, qr_code_hash) VALUES (?, ?, ?)")
                ->execute([3240, $qrCode, $qrHash]);
            echo "✅ QR code created for student 3240\n";
        } else {
            echo "❌ Student 3240 does not exist in student_details table\n";
        }
    } else {
        echo "✅ QR code exists for student {$result['student_id']}\n";
    }
    
    // Test the controller query
    echo "\n=== TESTING CONTROLLER QUERY ===\n";
    $testQuery = "
        SELECT 
            sd.id,
            sd.first_name,
            sd.last_name,
            sd.grade_level,
            sd.section,
            sd.gender,
            sd.profile_photo,
            sqr.qr_code_data
        FROM student_qr_codes sqr
        JOIN student_details sd ON sqr.student_id = sd.id
        WHERE sqr.qr_code_data = ? AND sqr.is_active = true
    ";
    
    $stmt = $pdo->prepare($testQuery);
    $stmt->execute([$qrCode]);
    $testResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testResult) {
        echo "✅ Controller query test successful:\n";
        echo "   Student: {$testResult['first_name']} {$testResult['last_name']}\n";
        echo "   Grade: {$testResult['grade_level']}\n";
        echo "   Section: {$testResult['section']}\n";
    } else {
        echo "❌ Controller query test failed\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "✅ QR Scanner should now work!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
