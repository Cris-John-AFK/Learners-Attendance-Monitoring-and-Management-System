<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING DATABASE STRUCTURE FOR QR CODES ===\n";
    echo "Scanned QR Code: LAMMS_STUDENT_19_1758809765_oex5qkZo\n\n";
    
    // Check if qr_codes table exists
    echo "1. CHECKING QR_CODES TABLE:\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name = 'qr_codes'");
    if ($stmt->rowCount() > 0) {
        echo "✅ qr_codes table exists\n";
        
        // Get table structure
        $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'qr_codes' ORDER BY ordinal_position");
        echo "Columns:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['column_name'] . " (" . $row['data_type'] . ")\n";
        }
        
        // Check sample data
        echo "\nSample QR codes data:\n";
        $stmt = $pdo->query('SELECT * FROM qr_codes LIMIT 5');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  Student ID: " . $row['student_id'] . ", QR: " . substr($row['qr_code_data'], 0, 30) . "...\n";
        }
    } else {
        echo "❌ qr_codes table does NOT exist\n";
    }
    
    // Check alternative table names
    echo "\n2. CHECKING ALTERNATIVE QR TABLE NAMES:\n";
    $alternativeNames = ['student_qr_codes', 'qrcodes', 'student_qrcodes', 'qr_code'];
    foreach ($alternativeNames as $tableName) {
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name = '$tableName'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Found table: $tableName\n";
            
            // Get structure
            $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$tableName' ORDER BY ordinal_position");
            echo "Columns:\n";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  - " . $row['column_name'] . " (" . $row['data_type'] . ")\n";
            }
            
            // Check sample data
            echo "\nSample data:\n";
            $stmt = $pdo->query("SELECT * FROM $tableName LIMIT 3");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                print_r($row);
                echo "\n";
            }
        } else {
            echo "❌ Table $tableName does not exist\n";
        }
    }
    
    // Check students table for QR-related columns
    echo "\n3. CHECKING STUDENTS TABLE FOR QR COLUMNS:\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'students' AND column_name LIKE '%qr%' ORDER BY ordinal_position");
    if ($stmt->rowCount() > 0) {
        echo "QR-related columns in students table:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['column_name'] . " (" . $row['data_type'] . ")\n";
        }
        
        // Check if student ID 19 exists
        echo "\n4. CHECKING STUDENT ID 19 (from scanned QR):\n";
        $stmt = $pdo->query('SELECT id, "firstName", "lastName", "studentId" FROM students WHERE id = 19');
        if ($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Student ID 19 exists:\n";
            echo "  Name: " . $student['firstName'] . " " . $student['lastName'] . "\n";
            echo "  Student ID: " . $student['studentId'] . "\n";
        } else {
            echo "❌ Student ID 19 does NOT exist\n";
        }
    } else {
        echo "❌ No QR-related columns found in students table\n";
    }
    
    // Check all tables that might contain QR data
    echo "\n5. SEARCHING ALL TABLES FOR QR-RELATED CONTENT:\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        try {
            // Check if any column contains QR-like data
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table' AND (column_name LIKE '%qr%' OR data_type = 'character varying')");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($columns as $column) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table WHERE $column LIKE '%LAMMS_STUDENT_%'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result['count'] > 0) {
                    echo "✅ Found QR-like data in table: $table, column: $column\n";
                    
                    // Show sample
                    $stmt = $pdo->query("SELECT $column FROM $table WHERE $column LIKE '%LAMMS_STUDENT_%' LIMIT 3");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "  Sample: " . substr($row[$column], 0, 40) . "...\n";
                    }
                }
            }
        } catch (Exception $e) {
            // Skip tables that can't be queried
            continue;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
