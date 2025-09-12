<?php

require_once 'lamms-backend/vendor/autoload.php';

// Database connection
$host = 'localhost';
$dbname = 'sakai_lamms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUGGING MARIA SANTOS ATTENDANCE DATA ===\n\n";
    
    // 1. Find Maria Santos teacher record
    echo "1. Finding Maria Santos teacher record:\n";
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE firstName LIKE '%Maria%' OR lastName LIKE '%Santos%'");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($teachers)) {
        echo "No teachers found with Maria Santos name\n";
        
        // Show all teachers
        echo "\nAll teachers in database:\n";
        $stmt = $pdo->prepare("SELECT id, firstName, lastName, email FROM teachers LIMIT 10");
        $stmt->execute();
        $allTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allTeachers as $teacher) {
            echo "ID: {$teacher['id']}, Name: {$teacher['firstName']} {$teacher['lastName']}, Email: {$teacher['email']}\n";
        }
    } else {
        foreach ($teachers as $teacher) {
            echo "Found: ID {$teacher['id']}, Name: {$teacher['firstName']} {$teacher['lastName']}\n";
        }
    }
    
    // 2. Check sections for any teacher (let's use teacher ID 1, 2, 3)
    echo "\n2. Checking sections for teachers:\n";
    $teacherIds = [1, 2, 3];
    
    foreach ($teacherIds as $teacherId) {
        echo "\nTeacher ID $teacherId sections:\n";
        $stmt = $pdo->prepare("
            SELECT s.*, t.firstName, t.lastName 
            FROM sections s 
            LEFT JOIN teachers t ON s.homeroom_teacher_id = t.id 
            WHERE s.homeroom_teacher_id = ?
        ");
        $stmt->execute([$teacherId]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($sections)) {
            echo "  No sections found for teacher $teacherId\n";
        } else {
            foreach ($sections as $section) {
                echo "  Section ID: {$section['id']}, Name: {$section['name']}, Grade: {$section['gradeLevel']}, Teacher: {$section['firstName']} {$section['lastName']}\n";
            }
        }
    }
    
    // 3. Check attendance sessions for section 3 (which we've been using)
    echo "\n3. Checking attendance sessions for section 3:\n";
    $stmt = $pdo->prepare("
        SELECT as_table.*, s.name as subject_name, sec.name as section_name
        FROM attendance_sessions as_table
        LEFT JOIN subjects s ON as_table.subject_id = s.id
        LEFT JOIN sections sec ON as_table.section_id = sec.id
        WHERE as_table.section_id = 3
        ORDER BY as_table.session_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "No attendance sessions found for section 3\n";
    } else {
        foreach ($sessions as $session) {
            echo "Session ID: {$session['id']}, Date: {$session['session_date']}, Subject: {$session['subject_name']}, Section: {$session['section_name']}\n";
        }
    }
    
    // 4. Check attendance records for these sessions
    echo "\n4. Checking attendance records for section 3 sessions:\n";
    $stmt = $pdo->prepare("
        SELECT ar.*, as_table.session_date, s.name as subject_name, 
               st.firstName as student_first, st.lastName as student_last,
               ast.name as status_name
        FROM attendance_records ar
        JOIN attendance_sessions as_table ON ar.attendance_session_id = as_table.id
        LEFT JOIN subjects s ON as_table.subject_id = s.id
        LEFT JOIN student_details st ON ar.student_id = st.id
        LEFT JOIN attendance_statuses ast ON ar.attendance_status_id = ast.id
        WHERE as_table.section_id = 3 AND ar.is_current_version = true
        ORDER BY as_table.session_date DESC, ar.student_id
        LIMIT 20
    ");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        echo "No attendance records found for section 3\n";
    } else {
        foreach ($records as $record) {
            echo "Date: {$record['session_date']}, Student: {$record['student_first']} {$record['student_last']}, Subject: {$record['subject_name']}, Status: {$record['status_name']}\n";
        }
    }
    
    // 5. Check what sections actually have attendance data
    echo "\n5. Sections with attendance data:\n";
    $stmt = $pdo->prepare("
        SELECT DISTINCT as_table.section_id, sec.name as section_name, sec.gradeLevel,
               COUNT(ar.id) as record_count
        FROM attendance_sessions as_table
        JOIN attendance_records ar ON as_table.id = ar.attendance_session_id
        LEFT JOIN sections sec ON as_table.section_id = sec.id
        WHERE ar.is_current_version = true
        GROUP BY as_table.section_id, sec.name, sec.gradeLevel
        ORDER BY record_count DESC
    ");
    $stmt->execute();
    $sectionsWithData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sectionsWithData as $section) {
        echo "Section ID: {$section['section_id']}, Name: {$section['section_name']}, Grade: {$section['gradeLevel']}, Records: {$section['record_count']}\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
