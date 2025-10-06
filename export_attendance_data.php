<?php
/**
 * Export Attendance Data to SQL File for Groupmates
 * 
 * This creates an SQL file that your groupmates can import into their database
 */

echo "📦 Exporting Attendance Data for Groupmates...\n";
echo "==============================================\n\n";

// Database connection settings from your .env file
$host = '127.0.0.1';
$port = '5432';
$dbname = 'lamms_db';
$username = 'postgres';
$password = 'postgres';

// Output file
$outputFile = 'LAMMS_ATTENDANCE_DATA.sql';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database\n\n";
    
    // Open output file
    $fp = fopen($outputFile, 'w');
    
    // Write header
    fwrite($fp, "-- LAMMS Attendance Data Export\n");
    fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fp, "-- Database: $dbname\n\n");
    fwrite($fp, "-- ========================================\n");
    fwrite($fp, "-- ATTENDANCE DATA FOR GROUPMATES\n");
    fwrite($fp, "-- ========================================\n\n");
    
    // Tables to export
    $tables = [
        'student_details' => 'Students Information',
        'sections' => 'Sections',
        'subjects' => 'Subjects',
        'grades' => 'Grade Levels',
        'attendance_sessions' => 'Attendance Sessions',
        'attendance_records' => 'Attendance Records',
        'attendance_statuses' => 'Attendance Status Types',
        'student_section' => 'Student-Section Assignments'
    ];
    
    foreach ($tables as $table => $description) {
        echo "📋 Exporting $table ($description)...\n";
        
        fwrite($fp, "\n-- ========================================\n");
        fwrite($fp, "-- $description\n");
        fwrite($fp, "-- ========================================\n\n");
        
        // Check if table exists
        $stmt = $pdo->query("SELECT to_regclass('public.$table') IS NOT NULL as exists");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC)['exists'];
        
        if (!$exists) {
            echo "   ⚠️  Table $table does not exist, skipping...\n";
            fwrite($fp, "-- Table $table does not exist in source database\n\n");
            continue;
        }
        
        // Get table structure
        $stmt = $pdo->query("SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
                            FROM information_schema.columns 
                            WHERE table_name = '$table' 
                            ORDER BY ordinal_position");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($columns)) {
            echo "   ⚠️  No columns found for $table, skipping...\n";
            continue;
        }
        
        // Create table structure
        fwrite($fp, "-- Create table if not exists\n");
        fwrite($fp, "CREATE TABLE IF NOT EXISTS $table (\n");
        
        $columnDefs = [];
        foreach ($columns as $col) {
            $def = "    " . $col['column_name'] . " " . strtoupper($col['data_type']);
            if ($col['character_maximum_length']) {
                $def .= "(" . $col['character_maximum_length'] . ")";
            }
            if ($col['is_nullable'] === 'NO') {
                $def .= " NOT NULL";
            }
            if ($col['column_default']) {
                $def .= " DEFAULT " . $col['column_default'];
            }
            $columnDefs[] = $def;
        }
        
        fwrite($fp, implode(",\n", $columnDefs) . "\n");
        fwrite($fp, ");\n\n");
        
        // Get data
        $stmt = $pdo->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            echo "   ℹ️  No data in $table\n";
            fwrite($fp, "-- No data to export\n\n");
            continue;
        }
        
        echo "   ✅ Found " . count($rows) . " rows\n";
        
        // Write data
        fwrite($fp, "-- Insert data\n");
        $columnNames = array_keys($rows[0]);
        
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } elseif (is_bool($value)) {
                    $values[] = $value ? 'true' : 'false';
                } else {
                    // Escape single quotes for SQL
                    $escaped = str_replace("'", "''", $value);
                    $values[] = "'" . $escaped . "'";
                }
            }
            
            fwrite($fp, "INSERT INTO $table (" . implode(", ", $columnNames) . ") VALUES (" . implode(", ", $values) . ");\n");
        }
        
        fwrite($fp, "\n");
    }
    
    // Write footer
    fwrite($fp, "\n-- ========================================\n");
    fwrite($fp, "-- Export Complete\n");
    fwrite($fp, "-- ========================================\n");
    
    fclose($fp);
    
    echo "\n✅ Export complete!\n";
    echo "📁 File created: $outputFile\n";
    echo "📊 File size: " . round(filesize($outputFile) / 1024, 2) . " KB\n\n";
    
    echo "📤 SEND THIS FILE TO YOUR GROUPMATES:\n";
    echo "   → $outputFile\n\n";
    
    echo "📝 INSTRUCTIONS FOR YOUR GROUPMATES:\n";
    echo "   1. Import this SQL file into their PostgreSQL database\n";
    echo "   2. They can use pgAdmin or command line:\n";
    echo "      psql -U postgres -d their_database < LAMMS_ATTENDANCE_DATA.sql\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
