<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/lamms-backend');
$dotenv->load();

// Database connection
try {
    $pdo = new PDO(
        "pgsql:host=" . $_ENV['DB_HOST'] . ";port=" . $_ENV['DB_PORT'] . ";dbname=" . $_ENV['DB_DATABASE'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully\n\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

echo "=== HOMEROOM TEACHER ASSIGNMENTS TEST ===\n\n";

// Check all teachers
echo "📋 ALL TEACHERS:\n";
$teachersQuery = "SELECT id, first_name, last_name FROM teachers ORDER BY id";
$teachers = $pdo->query($teachersQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($teachers as $teacher) {
    echo "  Teacher {$teacher['id']}: {$teacher['first_name']} {$teacher['last_name']}\n";
}

echo "\n📋 ALL SECTIONS WITH HOMEROOM TEACHERS:\n";
$sectionsQuery = "SELECT id, name, homeroom_teacher_id FROM sections ORDER BY id";
$sections = $pdo->query($sectionsQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($sections as $section) {
    $teacherName = "No homeroom teacher";
    if ($section['homeroom_teacher_id']) {
        $teacherQuery = "SELECT first_name, last_name FROM teachers WHERE id = ?";
        $stmt = $pdo->prepare($teacherQuery);
        $stmt->execute([$section['homeroom_teacher_id']]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($teacher) {
            $teacherName = "{$teacher['first_name']} {$teacher['last_name']} (ID: {$section['homeroom_teacher_id']})";
        }
    }
    echo "  Section {$section['id']}: {$section['name']} -> {$teacherName}\n";
}

echo "\n🔍 SPECIFIC TEACHER HOMEROOM ASSIGNMENTS:\n";

// Test Maria Santos (Teacher 1)
echo "\n--- Maria Santos (Teacher 1) ---\n";
$mariaQuery = "SELECT s.id, s.name FROM sections s WHERE s.homeroom_teacher_id = 1";
$mariaSection = $pdo->query($mariaQuery)->fetch(PDO::FETCH_ASSOC);
if ($mariaSection) {
    echo "✅ Maria Santos is homeroom teacher of: Section {$mariaSection['id']} - {$mariaSection['name']}\n";
} else {
    echo "❌ Maria Santos has no homeroom section assigned\n";
}

// Test Rosa Garcia (Teacher 3)
echo "\n--- Rosa Garcia (Teacher 3) ---\n";
$rosaQuery = "SELECT s.id, s.name FROM sections s WHERE s.homeroom_teacher_id = 3";
$rosaSection = $pdo->query($rosaQuery)->fetch(PDO::FETCH_ASSOC);
if ($rosaSection) {
    echo "✅ Rosa Garcia is homeroom teacher of: Section {$rosaSection['id']} - {$rosaSection['name']}\n";
} else {
    echo "❌ Rosa Garcia has no homeroom section assigned\n";
}

echo "\n🔧 FIXING HOMEROOM ASSIGNMENTS IF NEEDED:\n";

// Check if we need to fix assignments
$needsFix = false;

// Maria Santos should be homeroom teacher of Kinder One
$kinderOneQuery = "SELECT id, homeroom_teacher_id FROM sections WHERE name ILIKE '%kinder one%' OR name ILIKE '%kinder 1%'";
$kinderOne = $pdo->query($kinderOneQuery)->fetch(PDO::FETCH_ASSOC);

if ($kinderOne) {
    if ($kinderOne['homeroom_teacher_id'] != 1) {
        echo "🔧 Fixing: Setting Maria Santos (1) as homeroom teacher of Kinder One (Section {$kinderOne['id']})\n";
        $updateQuery = "UPDATE sections SET homeroom_teacher_id = 1 WHERE id = ?";
        $pdo->prepare($updateQuery)->execute([$kinderOne['id']]);
        $needsFix = true;
    } else {
        echo "✅ Maria Santos is already homeroom teacher of Kinder One\n";
    }
}

// Rosa Garcia should be homeroom teacher of Kinder Two
$kinderTwoQuery = "SELECT id, homeroom_teacher_id FROM sections WHERE name ILIKE '%kinder two%' OR name ILIKE '%kinder 2%'";
$kinderTwo = $pdo->query($kinderTwoQuery)->fetch(PDO::FETCH_ASSOC);

if ($kinderTwo) {
    if ($kinderTwo['homeroom_teacher_id'] != 3) {
        echo "🔧 Fixing: Setting Rosa Garcia (3) as homeroom teacher of Kinder Two (Section {$kinderTwo['id']})\n";
        $updateQuery = "UPDATE sections SET homeroom_teacher_id = 3 WHERE id = ?";
        $pdo->prepare($updateQuery)->execute([$kinderTwo['id']]);
        $needsFix = true;
    } else {
        echo "✅ Rosa Garcia is already homeroom teacher of Kinder Two\n";
    }
}

if ($needsFix) {
    echo "\n✅ Homeroom assignments have been fixed!\n";
} else {
    echo "\n✅ All homeroom assignments are correct!\n";
}

echo "\n=== FINAL VERIFICATION ===\n";

// Final verification
echo "\n📋 FINAL HOMEROOM ASSIGNMENTS:\n";
$finalQuery = "
    SELECT 
        s.id as section_id,
        s.name as section_name,
        t.id as teacher_id,
        t.first_name,
        t.last_name
    FROM sections s
    LEFT JOIN teachers t ON s.homeroom_teacher_id = t.id
    ORDER BY s.id
";

$finalResults = $pdo->query($finalQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($finalResults as $result) {
    $teacherInfo = $result['teacher_id'] 
        ? "{$result['first_name']} {$result['last_name']} (ID: {$result['teacher_id']})"
        : "No homeroom teacher";
    
    echo "  Section {$result['section_id']}: {$result['section_name']} -> {$teacherInfo}\n";
}

echo "\n🎯 EXPECTED RESULTS FOR ATTENDANCE RECORDS:\n";
echo "  - Maria Santos should see: Her homeroom section only\n";
echo "  - Rosa Garcia should see: Her homeroom section only\n";
echo "  - No dropdown needed - each teacher sees their assigned section automatically\n";

echo "\n✅ Test completed!\n";
?>
