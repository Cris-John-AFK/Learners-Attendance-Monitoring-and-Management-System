<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable('lamms-backend');
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

// Check what attendance tables exist
echo "=== CHECKING ATTENDANCE TABLES ===\n";
$tables = ['attendances', 'attendance_records', 'attendance_sessions', 'attendance_statuses'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Table '$table' exists\n";
    } catch (PDOException $e) {
        echo "❌ Table '$table' does not exist\n";
    }
}

echo "\n=== CHECKING GRADES AND STUDENTS ===\n";

// Check grades
try {
    $stmt = $pdo->query("SELECT id, name, code FROM grades WHERE is_active = true");
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Found " . count($grades) . " active grades:\n";
    foreach ($grades as $grade) {
        echo "  - Grade {$grade['id']}: {$grade['name']} ({$grade['code']})\n";
    }
} catch (PDOException $e) {
    echo "❌ Error fetching grades: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING STUDENT SECTIONS ===\n";

// Check student-section relationships
try {
    $stmt = $pdo->query("
        SELECT 
            g.name as grade_name,
            COUNT(DISTINCT ss.student_id) as student_count
        FROM student_section ss
        JOIN sections s ON ss.section_id = s.id
        JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
        JOIN grades g ON cg.grade_id = g.id
        WHERE ss.is_active = true
        GROUP BY g.id, g.name
        ORDER BY g.name
    ");
    $studentCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Student enrollment by grade:\n";
    foreach ($studentCounts as $count) {
        echo "  - {$count['grade_name']}: {$count['student_count']} students\n";
    }
} catch (PDOException $e) {
    echo "❌ Error fetching student counts: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING ATTENDANCE DATA ===\n";

// Check attendance records
try {
    // Try production attendance system first
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_records
        FROM attendance_records ar
        JOIN attendance_sessions ases ON ar.attendance_session_id = ases.id
        WHERE ases.session_date >= '2024-01-01'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Found {$result['total_records']} attendance records in production system\n";
    
    // Get sample data
    $stmt = $pdo->query("
        SELECT 
            ases.session_date,
            ar.attendance_status_id,
            COUNT(*) as count
        FROM attendance_records ar
        JOIN attendance_sessions ases ON ar.attendance_session_id = ases.id
        WHERE ases.session_date >= '2024-01-01'
        GROUP BY ases.session_date, ar.attendance_status_id
        ORDER BY ases.session_date DESC
        LIMIT 10
    ");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample attendance data:\n";
    foreach ($samples as $sample) {
        echo "  - Date: {$sample['session_date']}, Status ID: {$sample['attendance_status_id']}, Count: {$sample['count']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Production attendance system not available: " . $e->getMessage() . "\n";
    
    // Try basic attendance system
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total_records
            FROM attendances
            WHERE date >= '2024-01-01'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Found {$result['total_records']} attendance records in basic system\n";
        
        // Get sample data
        $stmt = $pdo->query("
            SELECT 
                date,
                status,
                COUNT(*) as count
            FROM attendances
            WHERE date >= '2024-01-01'
            GROUP BY date, status
            ORDER BY date DESC
            LIMIT 10
        ");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample attendance data:\n";
        foreach ($samples as $sample) {
            echo "  - Date: {$sample['date']}, Status: {$sample['status']}, Count: {$sample['count']}\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ Basic attendance system also not available: " . $e->getMessage() . "\n";
    }
}

echo "\n=== TESTING API ENDPOINT ===\n";

// Test the API endpoint
$url = 'http://localhost:8000/api/admin/attendance/analytics?date_range=current_year';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ API endpoint working (HTTP $httpCode)\n";
    echo "Response summary:\n";
    if (isset($data['data']['grades'])) {
        echo "  - Found " . count($data['data']['grades']) . " grades in response\n";
        foreach ($data['data']['grades'] as $grade) {
            echo "  - {$grade['grade_name']}: Present={$grade['present']}, Absent={$grade['absent']}, Late={$grade['late']}\n";
        }
    }
    if (isset($data['data']['summary'])) {
        echo "  - Overall attendance rate: {$data['data']['summary']['overall_attendance_rate']}%\n";
    }
} else {
    echo "❌ API endpoint failed (HTTP $httpCode)\n";
    echo "Response: $response\n";
}

echo "\nTest completed!\n";
?>
