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

echo "=== DEBUGGING STUDENT FILTERING FOR GRADE 3 ===\n\n";

// Step 1: Check Grade 3 details
echo "1. Grade 3 details:\n";
$stmt = $pdo->query("SELECT id, name, code FROM grades WHERE name = 'Grade 3' OR code = 'G3'");
$grade3 = $stmt->fetch(PDO::FETCH_ASSOC);
if ($grade3) {
    echo "   Grade ID: {$grade3['id']}, Name: {$grade3['name']}, Code: {$grade3['code']}\n";
} else {
    echo "   ❌ Grade 3 not found!\n";
    exit;
}

// Step 2: Check curriculum_grade relationship
echo "\n2. Curriculum-Grade relationship:\n";
$stmt = $pdo->prepare("SELECT id, curriculum_id FROM curriculum_grade WHERE grade_id = ?");
$stmt->execute([$grade3['id']]);
$curriculumGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Found " . count($curriculumGrades) . " curriculum-grade relationships:\n";
foreach ($curriculumGrades as $cg) {
    echo "   - CurriculumGrade ID: {$cg['id']}, Curriculum ID: {$cg['curriculum_id']}\n";
}

// Step 3: Check sections for Grade 3
echo "\n3. Sections for Grade 3:\n";
$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.curriculum_grade_id 
    FROM sections s 
    JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id 
    WHERE cg.grade_id = ?
");
$stmt->execute([$grade3['id']]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Found " . count($sections) . " sections:\n";
foreach ($sections as $section) {
    echo "   - Section ID: {$section['id']}, Name: {$section['name']}, CurriculumGrade ID: {$section['curriculum_grade_id']}\n";
}

// Step 4: Check students in Grade 3 sections
echo "\n4. Students in Grade 3 sections:\n";
$stmt = $pdo->prepare("
    SELECT 
        ss.student_id,
        sd.name as student_name,
        s.name as section_name,
        ss.is_active
    FROM student_section ss
    JOIN sections s ON ss.section_id = s.id
    JOIN curriculum_grade cg ON s.curriculum_grade_id = cg.id
    JOIN student_details sd ON ss.student_id = sd.id
    WHERE cg.grade_id = ?
    ORDER BY ss.is_active DESC, sd.name
");
$stmt->execute([$grade3['id']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Found " . count($students) . " student enrollments:\n";
foreach ($students as $student) {
    $status = $student['is_active'] ? 'ACTIVE' : 'INACTIVE';
    echo "   - Student ID: {$student['student_id']}, Name: {$student['student_name']}, Section: {$student['section_name']}, Status: $status\n";
}

// Step 5: Get active student IDs only
$activeStudentIds = [];
foreach ($students as $student) {
    if ($student['is_active']) {
        $activeStudentIds[] = $student['student_id'];
    }
}
echo "\n   Active student IDs for Grade 3: " . implode(', ', $activeStudentIds) . "\n";

// Step 6: Check attendance records for these specific students
echo "\n5. Attendance records for Grade 3 students:\n";
if (!empty($activeStudentIds)) {
    $placeholders = str_repeat('?,', count($activeStudentIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            ar.student_id,
            ar.attendance_status_id,
            ases.session_date,
            COUNT(*) as count
        FROM attendance_records ar
        JOIN attendance_sessions ases ON ar.attendance_session_id = ases.id
        WHERE ar.student_id IN ($placeholders)
        AND ases.session_date >= '2024-01-01'
        GROUP BY ar.student_id, ar.attendance_status_id, ases.session_date
        ORDER BY ases.session_date DESC, ar.student_id
        LIMIT 20
    ");
    $stmt->execute($activeStudentIds);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Found " . count($attendanceRecords) . " attendance record groups:\n";
    foreach ($attendanceRecords as $record) {
        echo "   - Student {$record['student_id']}, Date: {$record['session_date']}, Status: {$record['attendance_status_id']}, Count: {$record['count']}\n";
    }
    
    // Step 7: Get totals by status
    echo "\n6. Attendance totals for Grade 3 students:\n";
    $stmt = $pdo->prepare("
        SELECT 
            ar.attendance_status_id,
            COUNT(*) as total_count
        FROM attendance_records ar
        JOIN attendance_sessions ases ON ar.attendance_session_id = ases.id
        WHERE ar.student_id IN ($placeholders)
        AND ases.session_date >= '2024-06-01'
        GROUP BY ar.attendance_status_id
        ORDER BY ar.attendance_status_id
    ");
    $stmt->execute($activeStudentIds);
    $totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusNames = [1 => 'Present', 2 => 'Absent', 3 => 'Late', 4 => 'Excused'];
    foreach ($totals as $total) {
        $statusName = $statusNames[$total['attendance_status_id']] ?? 'Unknown';
        echo "   - Status {$total['attendance_status_id']} ($statusName): {$total['total_count']} records\n";
    }
} else {
    echo "   ❌ No active students found for Grade 3!\n";
}

echo "\n=== DEBUGGING COMPLETE ===\n";
?>
