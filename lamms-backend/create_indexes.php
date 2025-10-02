<?php

echo "🚀 Creating performance indexes for LAMMS attendance system\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Connect to PostgreSQL database with correct credentials
    $pdo = new PDO('pgsql:host=localhost;dbname=lamms_db', 'postgres', '1234');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $indexesCreated = 0;
    $indexesFailed = 0;
    
    // Define all indexes to create
    $indexes = [
        // Teacher Section Subject indexes
        ['table' => 'teacher_section_subject', 'name' => 'idx_tss_teacher_id', 'columns' => '(teacher_id)'],
        ['table' => 'teacher_section_subject', 'name' => 'idx_tss_section_id', 'columns' => '(section_id)'],
        ['table' => 'teacher_section_subject', 'name' => 'idx_tss_subject_id', 'columns' => '(subject_id)'],
        ['table' => 'teacher_section_subject', 'name' => 'idx_tss_teacher_section_subject', 'columns' => '(teacher_id, section_id, subject_id)'],
        ['table' => 'teacher_section_subject', 'name' => 'idx_tss_active', 'columns' => '(is_active)', 'where' => 'WHERE is_active = true'],
        
        // Attendance Sessions
        ['table' => 'attendance_sessions', 'name' => 'idx_attendance_sessions_teacher_id', 'columns' => '(teacher_id)'],
        ['table' => 'attendance_sessions', 'name' => 'idx_attendance_sessions_section_id', 'columns' => '(section_id)'],
        ['table' => 'attendance_sessions', 'name' => 'idx_attendance_sessions_subject_id', 'columns' => '(subject_id)'],
        ['table' => 'attendance_sessions', 'name' => 'idx_attendance_sessions_date', 'columns' => '(session_date)'],
        ['table' => 'attendance_sessions', 'name' => 'idx_attendance_sessions_composite', 'columns' => '(teacher_id, section_id, subject_id, session_date)'],
        
        // Attendance Records
        ['table' => 'attendance_records', 'name' => 'idx_attendance_records_student_id', 'columns' => '(student_id)'],
        ['table' => 'attendance_records', 'name' => 'idx_attendance_records_session_id', 'columns' => '(attendance_session_id)'],
        ['table' => 'attendance_records', 'name' => 'idx_attendance_records_status', 'columns' => '(attendance_status_code)'],
        ['table' => 'attendance_records', 'name' => 'idx_attendance_records_composite', 'columns' => '(student_id, attendance_session_id, attendance_status_code)'],
        
        // Student Section
        ['table' => 'student_section', 'name' => 'idx_student_section_student_id', 'columns' => '(student_id)'],
        ['table' => 'student_section', 'name' => 'idx_student_section_section_id', 'columns' => '(section_id)'],
        ['table' => 'student_section', 'name' => 'idx_student_section_active', 'columns' => '(is_active)', 'where' => 'WHERE is_active = true'],
        ['table' => 'student_section', 'name' => 'idx_student_section_composite', 'columns' => '(section_id, student_id)', 'where' => 'WHERE is_active = true'],
        
        // Student Details
        ['table' => 'student_details', 'name' => 'idx_student_details_student_id', 'columns' => '(student_id)'],
        ['table' => 'student_details', 'name' => 'idx_student_details_lrn', 'columns' => '(lrn)'],
        ['table' => 'student_details', 'name' => 'idx_student_details_status', 'columns' => '(status)'],
        ['table' => 'student_details', 'name' => 'idx_student_details_grade', 'columns' => '("gradeLevel")'],
        
        // Sections
        ['table' => 'sections', 'name' => 'idx_sections_curriculum_grade', 'columns' => '(curriculum_grade_id)'],
        ['table' => 'sections', 'name' => 'idx_sections_homeroom_teacher', 'columns' => '(homeroom_teacher_id)'],
        ['table' => 'sections', 'name' => 'idx_sections_active', 'columns' => '(is_active)', 'where' => 'WHERE is_active = true'],
        
        // Subject Schedules
        ['table' => 'subject_schedules', 'name' => 'idx_subject_schedules_section_id', 'columns' => '(section_id)'],
        ['table' => 'subject_schedules', 'name' => 'idx_subject_schedules_subject_id', 'columns' => '(subject_id)'],
        ['table' => 'subject_schedules', 'name' => 'idx_subject_schedules_teacher_id', 'columns' => '(teacher_id)'],
        ['table' => 'subject_schedules', 'name' => 'idx_subject_schedules_day', 'columns' => '(day_of_week)'],
        
        // Teachers and Users
        ['table' => 'teachers', 'name' => 'idx_teachers_user_id', 'columns' => '(user_id)'],
        ['table' => 'users', 'name' => 'idx_users_username', 'columns' => '(username)'],
        ['table' => 'users', 'name' => 'idx_users_role', 'columns' => '(role)'],
    ];
    
    // Create each index
    foreach ($indexes as $index) {
        $where = isset($index['where']) ? $index['where'] : '';
        $sql = "CREATE INDEX IF NOT EXISTS {$index['name']} ON {$index['table']}{$index['columns']} {$where}";
        
        try {
            $pdo->exec($sql);
            echo "✅ Created: {$index['name']} on {$index['table']}\n";
            $indexesCreated++;
        } catch (PDOException $e) {
            // Check if error is because index already exists
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Exists: {$index['name']} (already present)\n";
            } else {
                echo "❌ Failed: {$index['name']} - " . $e->getMessage() . "\n";
                $indexesFailed++;
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 Summary:\n";
    echo "   ✅ Indexes created/verified: $indexesCreated\n";
    echo "   ❌ Indexes failed: $indexesFailed\n";
    echo "   📈 Total indexes processed: " . count($indexes) . "\n";
    
    // Analyze tables to update statistics
    echo "\n🔍 Updating table statistics for query optimizer...\n";
    $tables = array_unique(array_column($indexes, 'table'));
    foreach ($tables as $table) {
        try {
            $pdo->exec("ANALYZE $table");
            echo "   ✅ Analyzed: $table\n";
        } catch (PDOException $e) {
            echo "   ⚠️  Could not analyze $table\n";
        }
    }
    
    echo "\n🎉 Database indexing complete! Your queries should now run much faster.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "💡 Make sure PostgreSQL is running and the database exists.\n";
}
