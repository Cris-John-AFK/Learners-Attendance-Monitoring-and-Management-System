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

echo "=== TEACHER AUTHENTICATION TEST ===\n\n";

// Check all teachers with their user accounts
echo "📋 ALL TEACHERS WITH USER ACCOUNTS:\n";
$teachersQuery = "
    SELECT 
        t.id as teacher_id,
        t.first_name,
        t.last_name,
        u.id as user_id,
        u.username,
        u.email,
        u.is_active
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.id
";

$teachers = $pdo->query($teachersQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($teachers as $teacher) {
    $userInfo = $teacher['user_id'] 
        ? "User ID: {$teacher['user_id']}, Username: {$teacher['username']}, Email: {$teacher['email']}, Active: " . ($teacher['is_active'] ? 'Yes' : 'No')
        : "No user account";
    
    echo "  Teacher {$teacher['teacher_id']}: {$teacher['first_name']} {$teacher['last_name']} -> {$userInfo}\n";
}

echo "\n🔍 CHECKING SPECIFIC TEACHERS:\n";

// Check Maria Santos
echo "\n--- Maria Santos (Teacher 1) ---\n";
$mariaQuery = "
    SELECT 
        t.id as teacher_id,
        t.first_name,
        t.last_name,
        u.id as user_id,
        u.username,
        u.email,
        s.id as section_id,
        s.name as section_name
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN sections s ON s.homeroom_teacher_id = t.id
    WHERE t.id = 1
";

$maria = $pdo->query($mariaQuery)->fetch(PDO::FETCH_ASSOC);
if ($maria) {
    echo "✅ Maria Santos found:\n";
    echo "   - Teacher ID: {$maria['teacher_id']}\n";
    echo "   - User ID: " . ($maria['user_id'] ?: 'None') . "\n";
    echo "   - Username: " . ($maria['username'] ?: 'None') . "\n";
    echo "   - Email: " . ($maria['email'] ?: 'None') . "\n";
    echo "   - Homeroom Section: " . ($maria['section_name'] ?: 'None') . "\n";
} else {
    echo "❌ Maria Santos not found\n";
}

// Check Rosa Garcia
echo "\n--- Rosa Garcia (Teacher 3) ---\n";
$rosaQuery = "
    SELECT 
        t.id as teacher_id,
        t.first_name,
        t.last_name,
        u.id as user_id,
        u.username,
        u.email,
        s.id as section_id,
        s.name as section_name
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN sections s ON s.homeroom_teacher_id = t.id
    WHERE t.id = 3
";

$rosa = $pdo->query($rosaQuery)->fetch(PDO::FETCH_ASSOC);
if ($rosa) {
    echo "✅ Rosa Garcia found:\n";
    echo "   - Teacher ID: {$rosa['teacher_id']}\n";
    echo "   - User ID: " . ($rosa['user_id'] ?: 'None') . "\n";
    echo "   - Username: " . ($rosa['username'] ?: 'None') . "\n";
    echo "   - Email: " . ($rosa['email'] ?: 'None') . "\n";
    echo "   - Homeroom Section: " . ($rosa['section_name'] ?: 'None') . "\n";
} else {
    echo "❌ Rosa Garcia not found\n";
}

echo "\n🔧 CREATING USER ACCOUNTS IF MISSING:\n";

// Create user account for Maria Santos if missing
if (!$maria['user_id']) {
    echo "Creating user account for Maria Santos...\n";
    $insertUserQuery = "
        INSERT INTO users (username, email, password, is_active, created_at, updated_at)
        VALUES (?, ?, ?, true, NOW(), NOW())
        RETURNING id
    ";
    $stmt = $pdo->prepare($insertUserQuery);
    $hashedPassword = password_hash('maria123', PASSWORD_DEFAULT);
    $stmt->execute(['maria.santos', 'maria.santos@school.edu', $hashedPassword]);
    $newUserId = $stmt->fetchColumn();
    
    // Link teacher to user
    $updateTeacherQuery = "UPDATE teachers SET user_id = ? WHERE id = 1";
    $pdo->prepare($updateTeacherQuery)->execute([$newUserId]);
    
    echo "✅ Created user account for Maria Santos (User ID: {$newUserId})\n";
    echo "   - Username: maria.santos\n";
    echo "   - Password: maria123\n";
} else {
    echo "✅ Maria Santos already has user account\n";
}

// Create user account for Rosa Garcia if missing
if (!$rosa['user_id']) {
    echo "Creating user account for Rosa Garcia...\n";
    $insertUserQuery = "
        INSERT INTO users (username, email, password, is_active, created_at, updated_at)
        VALUES (?, ?, ?, true, NOW(), NOW())
        RETURNING id
    ";
    $stmt = $pdo->prepare($insertUserQuery);
    $hashedPassword = password_hash('rosa123', PASSWORD_DEFAULT);
    $stmt->execute(['rosa.garcia', 'rosa.garcia@school.edu', $hashedPassword]);
    $newUserId = $stmt->fetchColumn();
    
    // Link teacher to user
    $updateTeacherQuery = "UPDATE teachers SET user_id = ? WHERE id = 3";
    $pdo->prepare($updateTeacherQuery)->execute([$newUserId]);
    
    echo "✅ Created user account for Rosa Garcia (User ID: {$newUserId})\n";
    echo "   - Username: rosa.garcia\n";
    echo "   - Password: rosa123\n";
} else {
    echo "✅ Rosa Garcia already has user account\n";
}

echo "\n=== FINAL VERIFICATION ===\n";

// Final check
$finalQuery = "
    SELECT 
        t.id as teacher_id,
        t.first_name,
        t.last_name,
        u.id as user_id,
        u.username,
        u.email,
        s.id as section_id,
        s.name as section_name
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN sections s ON s.homeroom_teacher_id = t.id
    WHERE t.id IN (1, 3)
    ORDER BY t.id
";

$finalResults = $pdo->query($finalQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($finalResults as $result) {
    echo "\n📋 {$result['first_name']} {$result['last_name']} (Teacher {$result['teacher_id']}):\n";
    echo "   - User ID: {$result['user_id']}\n";
    echo "   - Username: {$result['username']}\n";
    echo "   - Email: {$result['email']}\n";
    echo "   - Homeroom Section: {$result['section_name']}\n";
}

echo "\n🎯 AUTHENTICATION INSTRUCTIONS:\n";
echo "1. Maria Santos should login with: maria.santos / maria123\n";
echo "2. Rosa Garcia should login with: rosa.garcia / rosa123\n";
echo "3. After login, the system will automatically detect the correct teacher ID\n";
echo "4. Each teacher will see their own homeroom section in Attendance Records\n";

echo "\n✅ Teacher authentication setup completed!\n";
?>
