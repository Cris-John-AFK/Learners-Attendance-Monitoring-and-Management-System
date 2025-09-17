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

    echo "=== CLEARING ASSIGNMENTS AND RESETTING DATABASE ===\n\n";

    $pdo->beginTransaction();

    // 1. Clear all teacher assignments
    echo "1. Clearing teacher assignments...\n";
    $stmt = $pdo->prepare("DELETE FROM teacher_section_subject");
    $stmt->execute();
    $deletedAssignments = $stmt->rowCount();
    echo "   ✅ Deleted {$deletedAssignments} teacher assignments\n";

    // 2. Clear all sections
    echo "2. Clearing sections...\n";
    $stmt = $pdo->prepare("DELETE FROM sections");
    $stmt->execute();
    $deletedSections = $stmt->rowCount();
    echo "   ✅ Deleted {$deletedSections} sections\n";

    // 3. Clear curriculum_grade relationships
    echo "3. Clearing curriculum grade relationships...\n";
    $stmt = $pdo->prepare("DELETE FROM curriculum_grade");
    $stmt->execute();
    $deletedCurriculum = $stmt->rowCount();
    echo "   ✅ Deleted {$deletedCurriculum} curriculum grade relationships\n";

    // 4. Clear grades (but keep if you want to preserve them)
    echo "4. Clearing grades...\n";
    $stmt = $pdo->prepare("DELETE FROM grades");
    $stmt->execute();
    $deletedGrades = $stmt->rowCount();
    echo "   ✅ Deleted {$deletedGrades} grades\n";

    // 5. Reset auto-increment sequences
    echo "5. Resetting sequences...\n";
    $sequences = [
        'teacher_section_subject_id_seq',
        'sections_id_seq',
        'curriculum_grade_id_seq',
        'grades_id_seq'
    ];

    foreach ($sequences as $sequence) {
        try {
            $stmt = $pdo->prepare("ALTER SEQUENCE {$sequence} RESTART WITH 1");
            $stmt->execute();
            echo "   ✅ Reset {$sequence}\n";
        } catch (Exception $e) {
            echo "   ⚠️  Could not reset {$sequence}: " . $e->getMessage() . "\n";
        }
    }

    $pdo->commit();

    echo "\n6. Verifying cleanup...\n";
    
    // Check remaining data
    $tables = [
        'teacher_section_subject' => 'Teacher assignments',
        'sections' => 'Sections',
        'curriculum_grade' => 'Curriculum grades',
        'grades' => 'Grades',
        'teachers' => 'Teachers (should remain)',
        'subjects' => 'Subjects (should remain)',
        'users' => 'Users (should remain)'
    ];

    foreach ($tables as $table => $description) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table}");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "   📊 {$description}: {$count} records\n";
    }

    echo "\n🎉 DATABASE RESET COMPLETED!\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "✅ All teacher assignments cleared\n";
    echo "✅ All sections removed\n";
    echo "✅ All grades removed\n";
    echo "✅ Teachers and subjects preserved\n";
    echo "\nYou can now manually create:\n";
    echo "- Grade levels through the admin interface\n";
    echo "- Sections for each grade\n";
    echo "- Teacher assignments to sections/subjects\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
