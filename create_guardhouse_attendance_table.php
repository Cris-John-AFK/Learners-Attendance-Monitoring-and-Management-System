<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CREATING GUARDHOUSE ATTENDANCE TABLE ===\n";
    
    // Create guardhouse_attendance table
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS guardhouse_attendance (
            id SERIAL PRIMARY KEY,
            student_id INTEGER NOT NULL,
            qr_code_data VARCHAR(255) NOT NULL,
            record_type VARCHAR(20) NOT NULL CHECK (record_type IN ('check-in', 'check-out')),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date DATE DEFAULT CURRENT_DATE,
            guard_name VARCHAR(100) DEFAULT 'Bread Doe',
            guard_id VARCHAR(20) DEFAULT 'G-12345',
            is_manual BOOLEAN DEFAULT FALSE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );
    ";
    
    $pdo->exec($createTableSQL);
    echo "✅ guardhouse_attendance table created successfully\n";
    
    // Create index for better performance
    $createIndexSQL = "
        CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_student_date 
        ON guardhouse_attendance(student_id, date);
        
        CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_timestamp 
        ON guardhouse_attendance(timestamp DESC);
    ";
    
    $pdo->exec($createIndexSQL);
    echo "✅ Indexes created successfully\n";
    
    // Check if students have default photos, if not add them
    echo "\n=== CHECKING STUDENT PHOTOS ===\n";
    
    // First, let's see current student structure
    $stmt = $pdo->query("SELECT id, first_name, last_name, gender, photo_path FROM students LIMIT 5");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo "❌ No students found in database\n";
    } else {
        echo "Sample students:\n";
        foreach ($students as $student) {
            echo "ID: {$student['id']}, Name: {$student['first_name']} {$student['last_name']}, Gender: " . ($student['gender'] ?? 'NULL') . ", Photo: " . ($student['photo_path'] ?? 'NULL') . "\n";
        }
        
        // Update students without photos to have default photos based on gender
        $updatePhotosSQL = "
            UPDATE students 
            SET photo_path = CASE 
                WHEN gender = 'male' OR gender = 'M' THEN '/demo/images/avatar/default-male-student.png'
                WHEN gender = 'female' OR gender = 'F' THEN '/demo/images/avatar/default-female-student.png'
                ELSE '/demo/images/avatar/default-student.png'
            END
            WHERE photo_path IS NULL OR photo_path = ''
        ";
        
        $affectedRows = $pdo->exec($updatePhotosSQL);
        echo "✅ Updated {$affectedRows} students with default photos\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "✅ Database structure ready for guardhouse attendance system\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
