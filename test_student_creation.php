<?php
// Test direct student creation to debug the issue
try {
    // PostgreSQL database configuration
    $host = '127.0.0.1';
    $port = '5432';
    $dbname = 'lamms_db';
    $username = 'postgres';
    $password = '1234';

    // Create PDO connection for PostgreSQL
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ PostgreSQL connection successful!\n";

    // Test data
    $testStudentId = 'TEST' . time();
    $testData = [
        'studentId' => $testStudentId,
        'student_id' => $testStudentId,
        'name' => 'Test Student',
        'firstName' => 'Test',
        'lastName' => 'Student',
        'gradeLevel' => 'Grade 1',
        'status' => 'Enrolled',
        'isActive' => true,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Insert test student (PostgreSQL syntax)
    $sql = 'INSERT INTO student_details ("studentId", student_id, name, "firstName", "lastName", "gradeLevel", status, "isActive", created_at, updated_at)
            VALUES (:studentId, :student_id, :name, :firstName, :lastName, :gradeLevel, :status, :isActive, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($testData);

    if ($result) {
        echo "✅ Test student created successfully with ID: $testStudentId\n";

        // Verify the record exists
        $stmt = $pdo->prepare('SELECT * FROM student_details WHERE "studentId" = ?');
        $stmt->execute([$testStudentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            echo "✅ Student record verified in database\n";
            echo "📋 Student data: " . json_encode($student, JSON_PRETTY_PRINT) . "\n";
        }

        // Clean up test data
        $stmt = $pdo->prepare('DELETE FROM student_details WHERE "studentId" = ?');
        $stmt->execute([$testStudentId]);
        echo "🧹 Test data cleaned up\n";

    } else {
        echo "❌ Failed to create test student\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "\n🔧 Please check:\n";
    echo "   1. PostgreSQL service is running\n";
    echo "   2. Database 'sakai_lamms' exists in pgAdmin4\n";
    echo "   3. Username and password are correct\n";
    echo "   4. Port 5432 is accessible\n";
}
?>
