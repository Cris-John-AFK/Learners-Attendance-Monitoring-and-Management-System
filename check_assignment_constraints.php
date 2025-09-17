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

    echo "=== CHECKING TEACHER ASSIGNMENT CONSTRAINTS ===\n\n";

    // Check constraints on teacher_section_subject table
    $stmt = $pdo->prepare("
        SELECT 
            conname as constraint_name,
            pg_get_constraintdef(c.oid) as constraint_definition
        FROM pg_constraint c
        JOIN pg_class t ON c.conrelid = t.oid
        WHERE t.relname = 'teacher_section_subject'
        AND c.contype = 'c'
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($constraints)) {
        echo "No check constraints found on teacher_section_subject table\n";
    } else {
        echo "Check constraints on teacher_section_subject table:\n";
        foreach ($constraints as $constraint) {
            echo "- {$constraint['constraint_name']}: {$constraint['constraint_definition']}\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
