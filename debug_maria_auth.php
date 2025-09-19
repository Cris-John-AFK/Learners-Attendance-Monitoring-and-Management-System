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

echo "=== DEBUGGING MARIA SANTOS AUTHENTICATION ===\n\n";

// Check Maria Santos user account details
echo "🔍 CHECKING MARIA SANTOS USER ACCOUNT:\n";
$mariaUserQuery = "
    SELECT 
        u.id as user_id,
        u.username,
        u.email,
        u.role,
        u.is_active,
        t.id as teacher_id,
        t.first_name,
        t.last_name
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.username = 'maria.santos'
";

$mariaUser = $pdo->query($mariaUserQuery)->fetch(PDO::FETCH_ASSOC);

if ($mariaUser) {
    echo "✅ Maria Santos user found:\n";
    echo "   - User ID: {$mariaUser['user_id']}\n";
    echo "   - Username: {$mariaUser['username']}\n";
    echo "   - Email: {$mariaUser['email']}\n";
    echo "   - Role: " . ($mariaUser['role'] ?: 'NULL') . "\n";
    echo "   - Active: " . ($mariaUser['is_active'] ? 'Yes' : 'No') . "\n";
    echo "   - Teacher ID: " . ($mariaUser['teacher_id'] ?: 'NULL') . "\n";
    echo "   - Teacher Name: " . ($mariaUser['first_name'] ? $mariaUser['first_name'] . ' ' . $mariaUser['last_name'] : 'NULL') . "\n";
} else {
    echo "❌ Maria Santos user not found\n";
}

// Check if role is missing and fix it
if ($mariaUser && !$mariaUser['role']) {
    echo "\n🔧 FIXING MISSING ROLE:\n";
    $updateRoleQuery = "UPDATE users SET role = 'teacher' WHERE username = 'maria.santos'";
    $pdo->exec($updateRoleQuery);
    echo "✅ Set role to 'teacher' for maria.santos\n";
}

// Test authentication flow manually
echo "\n🧪 TESTING AUTHENTICATION FLOW:\n";

// Simulate login process
$username = 'maria.santos';
$password = 'password123';

echo "1. Looking for user with username: {$username}\n";
$authQuery = "
    SELECT * FROM users 
    WHERE username = ? 
    AND role = 'teacher' 
    AND is_active = true
";
$stmt = $pdo->prepare($authQuery);
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ User found for authentication\n";
    echo "   - User ID: {$user['id']}\n";
    echo "   - Role: {$user['role']}\n";
    
    // Check password (assuming it's hashed with password_hash)
    if (password_verify($password, $user['password'])) {
        echo "✅ Password verification successful\n";
    } else {
        echo "❌ Password verification failed\n";
        echo "   - Trying to update password...\n";
        $newPassword = password_hash($password, PASSWORD_DEFAULT);
        $updatePasswordQuery = "UPDATE users SET password = ? WHERE id = ?";
        $pdo->prepare($updatePasswordQuery)->execute([$newPassword, $user['id']]);
        echo "✅ Password updated for maria.santos\n";
    }
    
    // Get teacher details
    echo "\n2. Looking for teacher with user_id: {$user['id']}\n";
    $teacherQuery = "SELECT * FROM teachers WHERE user_id = ?";
    $stmt = $pdo->prepare($teacherQuery);
    $stmt->execute([$user['id']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        echo "✅ Teacher found:\n";
        echo "   - Teacher ID: {$teacher['id']}\n";
        echo "   - Name: {$teacher['first_name']} {$teacher['last_name']}\n";
        
        // Check assignments
        echo "\n3. Checking teacher assignments for Teacher ID: {$teacher['id']}\n";
        $assignmentsQuery = "
            SELECT 
                tss.id as assignment_id,
                tss.section_id,
                tss.subject_id,
                tss.role,
                tss.is_primary,
                s.name as section_name,
                COALESCE(sub.name, 'Homeroom') as subject_name
            FROM teacher_section_subject tss
            JOIN sections s ON tss.section_id = s.id
            LEFT JOIN subjects sub ON tss.subject_id = sub.id
            WHERE tss.teacher_id = ?
            AND tss.is_active = true
            AND tss.deleted_at IS NULL
        ";
        $stmt = $pdo->prepare($assignmentsQuery);
        $stmt->execute([$teacher['id']]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($assignments) {
            echo "✅ Found " . count($assignments) . " assignments:\n";
            foreach ($assignments as $assignment) {
                echo "   - Section: {$assignment['section_name']} | Subject: {$assignment['subject_name']} | Role: {$assignment['role']}\n";
            }
        } else {
            echo "❌ No assignments found\n";
        }
        
        // Check homeroom section
        echo "\n4. Checking homeroom section for Teacher ID: {$teacher['id']}\n";
        $homeroomQuery = "SELECT id, name FROM sections WHERE homeroom_teacher_id = ?";
        $stmt = $pdo->prepare($homeroomQuery);
        $stmt->execute([$teacher['id']]);
        $homeroom = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($homeroom) {
            echo "✅ Homeroom section found:\n";
            echo "   - Section ID: {$homeroom['id']}\n";
            echo "   - Section Name: {$homeroom['name']}\n";
        } else {
            echo "❌ No homeroom section found\n";
        }
        
    } else {
        echo "❌ Teacher not found for user_id: {$user['id']}\n";
    }
    
} else {
    echo "❌ User not found for authentication\n";
}

echo "\n=== SUMMARY ===\n";
echo "Expected Result: Maria Santos (Teacher ID 1) should see Kinder One\n";
echo "Authentication should return Teacher ID 1, not Teacher ID 3\n";

echo "\n✅ Debug completed!\n";
?>
