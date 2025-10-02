<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$dbname = 'lamms_db';
$username = 'postgres';
$password = 'admin';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== GUARDHOUSE REPORTS API TEST ===\n\n";
    
    // 1. Check if guardhouse_attendance table exists
    echo "1. Checking guardhouse_attendance table:\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'guardhouse_attendance'");
    if ($stmt->fetchColumn() > 0) {
        echo "✅ guardhouse_attendance table exists\n";
        
        // Get today's records
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM guardhouse_attendance WHERE date = ?");
        $stmt->execute([$today]);
        $count = $stmt->fetchColumn();
        echo "   - Today's records: $count\n";
    } else {
        echo "❌ guardhouse_attendance table NOT found\n";
    }
    
    // 2. Check if archive tables exist
    echo "\n2. Checking archive tables:\n";
    $archiveTables = ['guardhouse_archive_sessions', 'guardhouse_archived_records'];
    foreach ($archiveTables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = '$table'");
        if ($stmt->fetchColumn() > 0) {
            echo "✅ $table exists\n";
        } else {
            echo "❌ $table NOT found - Run migration: php artisan migrate\n";
        }
    }
    
    // 3. Test live feed query
    echo "\n3. Testing live feed query:\n";
    try {
        $today = date('Y-m-d');
        $query = '
            SELECT 
                ga.id,
                ga.student_id,
                CONCAT(sd."firstName", \' \', sd."lastName") as student_name,
                sd."gradeLevel" as grade_level,
                sd.section,
                ga.timestamp,
                ga.record_type
            FROM guardhouse_attendance ga
            JOIN student_details sd ON ga.student_id = sd.id
            WHERE ga.date = ?
            AND ga.record_type = ?
            ORDER BY ga.timestamp DESC
            LIMIT 5
        ';
        
        // Test check-ins
        $stmt = $pdo->prepare($query);
        $stmt->execute([$today, 'check-in']);
        $checkIns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Check-ins found: " . count($checkIns) . "\n";
        
        // Test check-outs
        $stmt = $pdo->prepare($query);
        $stmt->execute([$today, 'check-out']);
        $checkOuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Check-outs found: " . count($checkOuts) . "\n";
        
    } catch (Exception $e) {
        echo "❌ Error in live feed query: " . $e->getMessage() . "\n";
    }
    
    // 4. Test student_details join
    echo "\n4. Testing student_details join:\n";
    try {
        $stmt = $pdo->query('
            SELECT COUNT(*) 
            FROM guardhouse_attendance ga
            JOIN student_details sd ON ga.student_id = sd.id
        ');
        $count = $stmt->fetchColumn();
        echo "✅ Successfully joined guardhouse_attendance with student_details\n";
        echo "   Total joined records: $count\n";
    } catch (Exception $e) {
        echo "❌ Error joining tables: " . $e->getMessage() . "\n";
    }
    
    // 5. Sample data for testing
    echo "\n5. Sample guardhouse records:\n";
    $stmt = $pdo->query('
        SELECT 
            ga.id,
            ga.student_id,
            ga.record_type,
            ga.date,
            ga.timestamp
        FROM guardhouse_attendance ga
        ORDER BY ga.timestamp DESC
        LIMIT 5
    ');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($records) > 0) {
        foreach ($records as $record) {
            echo "   - ID: {$record['id']}, Student: {$record['student_id']}, ";
            echo "Type: {$record['record_type']}, Date: {$record['date']}\n";
        }
    } else {
        echo "   No records found in guardhouse_attendance\n";
    }
    
    echo "\n=== API ENDPOINTS TO TEST ===\n";
    echo "1. GET  /api/guardhouse/live-feed\n";
    echo "2. POST /api/guardhouse/toggle-scanner\n";
    echo "3. POST /api/guardhouse/archive-session\n";
    echo "4. GET  /api/guardhouse/archived-sessions\n";
    
    echo "\n=== NEXT STEPS ===\n";
    echo "1. Run migration if archive tables don't exist:\n";
    echo "   cd lamms-backend && php artisan migrate\n";
    echo "2. Test the API endpoints in browser/Postman\n";
    echo "3. Check the GuardHouse Reports page in admin panel\n";
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ]);
}
?>
