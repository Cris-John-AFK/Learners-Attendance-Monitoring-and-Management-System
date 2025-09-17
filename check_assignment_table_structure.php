<?php
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Database connection
    $pdo = new PDO(
        "pgsql:host=" . $_ENV['DB_HOST'] . ";port=" . $_ENV['DB_PORT'] . ";dbname=" . $_ENV['DB_DATABASE'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEACHER ASSIGNMENT TABLE STRUCTURE CHECK ===\n\n";

    // 1. Check if teacher_section_subject table exists and its structure
    echo "1. Checking teacher_section_subject table structure:\n";
    $stmt = $pdo->prepare("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'teacher_section_subject' 
        ORDER BY ordinal_position
    ");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "❌ teacher_section_subject table does not exist!\n";
        
        // Check what tables do exist
        echo "\n2. Available tables in database:\n";
        $stmt = $pdo->prepare("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tables as $table) {
            echo "- {$table['table_name']}\n";
        }
    } else {
        echo "✅ teacher_section_subject table exists with columns:\n";
        foreach ($columns as $column) {
            echo "- {$column['column_name']} ({$column['data_type']}) - Nullable: {$column['is_nullable']}\n";
        }
        
        // 3. Check if there are any records in the table
        echo "\n3. Checking records in teacher_section_subject table:\n";
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM teacher_section_subject");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Total records: {$count['total']}\n";
        
        if ($count['total'] > 0) {
            // Show sample records
            echo "\n4. Sample records:\n";
            $stmt = $pdo->prepare("
                SELECT 
                    id, teacher_id, section_id, subject_id, role, is_active, 
                    CASE WHEN deleted_at IS NULL THEN 'No' ELSE 'Yes' END as is_deleted
                FROM teacher_section_subject 
                LIMIT 10
            ");
            $stmt->execute();
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($samples as $sample) {
                echo "- ID: {$sample['id']}, Teacher: {$sample['teacher_id']}, Section: {$sample['section_id']}, Subject: {$sample['subject_id']}, Role: {$sample['role']}, Active: " . ($sample['is_active'] ? 'Yes' : 'No') . ", Deleted: {$sample['is_deleted']}\n";
            }
        }
    }

    // 4. Check if migrations have been run
    echo "\n5. Checking migration status:\n";
    $stmt = $pdo->prepare("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_name = 'migrations'
    ");
    $stmt->execute();
    $migrationTable = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($migrationTable) {
        echo "✅ Migrations table exists\n";
        
        // Check for teacher_section_subject migration
        $stmt = $pdo->prepare("
            SELECT migration, batch 
            FROM migrations 
            WHERE migration LIKE '%teacher_section_subject%'
            ORDER BY batch DESC
        ");
        $stmt->execute();
        $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($migrations)) {
            echo "❌ No teacher_section_subject migration found\n";
        } else {
            echo "✅ Found teacher_section_subject migrations:\n";
            foreach ($migrations as $migration) {
                echo "- {$migration['migration']} (Batch: {$migration['batch']})\n";
            }
        }
    } else {
        echo "❌ No migrations table found\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
