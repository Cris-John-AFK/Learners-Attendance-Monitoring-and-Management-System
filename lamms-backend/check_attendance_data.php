<?php

echo "🔍 Checking Attendance Data for Maria Santos (Teacher ID: 1)\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // Connect to PostgreSQL database
    $pdo = new PDO('pgsql:host=localhost;dbname=lamms_db', 'postgres', '1234');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. CHECKING TEACHER ASSIGNMENTS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT tss.*, s.name as section_name, sub.name as subject_name
        FROM teacher_section_subject tss
        JOIN sections s ON tss.section_id = s.id
        JOIN subjects sub ON tss.subject_id = sub.id
        WHERE tss.teacher_id = 1 AND tss.is_active = true
    ");
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($assignments as $assignment) {
        echo "✅ Section: {$assignment['section_name']} | Subject: {$assignment['subject_name']} | Role: {$assignment['role']}\n";
    }
    
    echo "\n2. CHECKING ATTENDANCE SESSIONS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT ases.*, s.name as section_name, sub.name as subject_name
        FROM attendance_sessions ases
        LEFT JOIN sections s ON ases.section_id = s.id
        LEFT JOIN subjects sub ON ases.subject_id = sub.id
        WHERE ases.teacher_id = 1
        ORDER BY ases.session_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "❌ NO ATTENDANCE SESSIONS found for Teacher ID 1\n";
    } else {
        foreach ($sessions as $session) {
            echo "📅 Date: {$session['session_date']} | Section: {$session['section_name']} | Subject: {$session['subject_name']} | ID: {$session['id']}\n";
        }
    }
    
    echo "\n3. CHECKING ATTENDANCE RECORDS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT ar.*, ases.session_date, ast.code as status_code, ast.name as status_name
        FROM attendance_records ar
        JOIN attendance_sessions ases ON ar.attendance_session_id = ases.id
        JOIN attendance_statuses ast ON ar.attendance_status_id = ast.id
        WHERE ases.teacher_id = 1
        ORDER BY ases.session_date DESC, ar.id DESC
        LIMIT 20
    ");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        echo "❌ NO ATTENDANCE RECORDS found for Teacher ID 1\n";
    } else {
        echo "Found " . count($records) . " attendance records:\n";
        foreach ($records as $record) {
            echo "📋 Date: {$record['session_date']} | Student: {$record['student_id']} | Status: {$record['status_code']} ({$record['status_name']})\n";
        }
    }
    
    echo "\n4. CHECKING DATE RANGES:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    // Check what date ranges have data
    $stmt = $pdo->prepare("
        SELECT 
            DATE_TRUNC('week', ases.session_date) as week_start,
            COUNT(*) as session_count,
            COUNT(ar.id) as record_count
        FROM attendance_sessions ases
        LEFT JOIN attendance_records ar ON ases.id = ar.attendance_session_id
        WHERE ases.teacher_id = 1
        GROUP BY DATE_TRUNC('week', ases.session_date)
        ORDER BY week_start DESC
    ");
    $stmt->execute();
    $weekData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($weekData)) {
        echo "❌ NO WEEKLY DATA found\n";
    } else {
        foreach ($weekData as $week) {
            echo "📊 Week of {$week['week_start']}: {$week['session_count']} sessions, {$week['record_count']} records\n";
        }
    }
    
    echo "\n5. CHECKING RECENT ATTENDANCE (Last 30 days):\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            ases.session_date,
            COUNT(ar.id) as total_records,
            SUM(CASE WHEN ast.code = 'P' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ast.code = 'A' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN ast.code = 'L' THEN 1 ELSE 0 END) as late_count
        FROM attendance_sessions ases
        LEFT JOIN attendance_records ar ON ases.id = ar.attendance_session_id
        LEFT JOIN attendance_statuses ast ON ar.attendance_status_id = ast.id
        WHERE ases.teacher_id = 1 
        AND ases.session_date >= CURRENT_DATE - INTERVAL '30 days'
        GROUP BY ases.session_date
        ORDER BY ases.session_date DESC
    ");
    $stmt->execute();
    $recentData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recentData)) {
        echo "❌ NO RECENT ATTENDANCE DATA (last 30 days)\n";
        echo "\n💡 SUGGESTION: You need to create attendance sessions and mark attendance!\n";
        echo "   Go to: Teacher Dashboard → Subject Attendance → Create Session → Mark Attendance\n";
    } else {
        foreach ($recentData as $day) {
            echo "📅 {$day['session_date']}: {$day['present_count']} Present, {$day['absent_count']} Absent, {$day['late_count']} Late (Total: {$day['total_records']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
