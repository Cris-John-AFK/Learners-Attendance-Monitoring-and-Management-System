<?php
/**
 * LAMMS Database Export Script
 * This script exports your PostgreSQL database to a SQL file
 * that your groupmates can import into their own database
 */

// Database configuration
$host = '127.0.0.1';
$port = '5432';
$dbname = 'lamms_db';
$username = 'postgres';
$password = 'postgres'; // CHANGE THIS to your actual PostgreSQL password

// Output file
$outputFile = __DIR__ . '/LAMMS_DATABASE_EXPORT_' . date('Y-m-d_His') . '.sql';

try {
    // Connect to PostgreSQL
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

    if (!$conn) {
        die("Connection failed: " . pg_last_error());
    }

    echo "✅ Connected to database successfully!\n";
    echo "📊 Starting export...\n\n";

    // Open output file
    $file = fopen($outputFile, 'w');

    // Write header
    fwrite($file, "-- ========================================\n");
    fwrite($file, "-- LAMMS DATABASE EXPORT\n");
    fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Database: $dbname\n");
    fwrite($file, "-- ========================================\n\n");

    fwrite($file, "-- INSTRUCTIONS FOR YOUR GROUPMATE:\n");
    fwrite($file, "-- 1. Create a new PostgreSQL database named 'lamms_db'\n");
    fwrite($file, "-- 2. Run all migrations first: php artisan migrate\n");
    fwrite($file, "-- 3. Then run this SQL file in pgAdmin or psql\n");
    fwrite($file, "-- 4. This will populate your database with the same data\n\n");

    // Tables to export (in order to respect foreign key constraints)
    $tables = [
        'users',
        'teachers',
        'curriculums',
        'grades',
        'curriculum_grade',
        'sections',
        'subjects',
        'student_details',
        'student_section',
        'teacher_section_subject',
        'attendance_statuses',
        'attendance_sessions',
        'attendance_records',
        'guardhouse_attendance',
        'student_qr_codes',
        'subject_schedules',
        'class_schedules',
        'submitted_sf2_reports'
    ];

    foreach ($tables as $table) {
        echo "📦 Exporting table: $table\n";

        // Check if table exists
        $checkQuery = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')";
        $result = pg_query($conn, $checkQuery);
        $exists = pg_fetch_result($result, 0, 0);

        if ($exists === 'f') {
            echo "   ⚠️  Table $table does not exist, skipping...\n";
            continue;
        }

        // Get row count
        $countResult = pg_query($conn, "SELECT COUNT(*) FROM $table");
        $count = pg_fetch_result($countResult, 0, 0);

        if ($count == 0) {
            echo "   ℹ️  Table $table is empty, skipping...\n";
            continue;
        }

        echo "   📊 Found $count rows\n";

        fwrite($file, "\n-- ========================================\n");
        fwrite($file, "-- TABLE: $table ($count rows)\n");
        fwrite($file, "-- ========================================\n\n");

        // Get all data
        $query = "SELECT * FROM $table";
        $result = pg_query($conn, $query);

        if (!$result) {
            echo "   ❌ Error querying $table: " . pg_last_error($conn) . "\n";
            continue;
        }

        // Get column names
        $numFields = pg_num_fields($result);
        $columns = [];
        for ($i = 0; $i < $numFields; $i++) {
            $columns[] = pg_field_name($result, $i);
        }

        $columnList = implode(', ', $columns);

        // Write INSERT statements
        $insertCount = 0;
        while ($row = pg_fetch_assoc($result)) {
            $values = [];
            foreach ($columns as $col) {
                $value = $row[$col];

                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_bool($value)) {
                    $values[] = $value ? 'true' : 'false';
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    // Escape single quotes for PostgreSQL
                    $escaped = str_replace("'", "''", $value);
                    $values[] = "'" . $escaped . "'";
                }
            }

            $valueList = implode(', ', $values);
            $sql = "INSERT INTO $table ($columnList) VALUES ($valueList) ON CONFLICT DO NOTHING;\n";
            fwrite($file, $sql);

            $insertCount++;
        }

        echo "   ✅ Exported $insertCount rows\n";
    }

    // Write footer
    fwrite($file, "\n-- ========================================\n");
    fwrite($file, "-- EXPORT COMPLETE\n");
    fwrite($file, "-- Total tables exported: " . count($tables) . "\n");
    fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- ========================================\n");

    fclose($file);
    pg_close($conn);

    echo "\n✅ Export completed successfully!\n";
    echo "📁 File saved to: $outputFile\n";
    echo "📊 File size: " . number_format(filesize($outputFile) / 1024, 2) . " KB\n\n";
    echo "🎉 You can now share this file with your groupmates!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
