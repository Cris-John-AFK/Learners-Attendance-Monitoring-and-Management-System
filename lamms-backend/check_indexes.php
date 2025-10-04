<?php

echo "🔍 Checking Existing Database Indexes in LAMMS\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Connect to PostgreSQL database with correct credentials
    $pdo = new PDO('pgsql:host=localhost;dbname=lamms_db', 'postgres', '1234');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get all indexes on our important tables
    $stmt = $pdo->query("
        SELECT 
            tablename,
            indexname,
            indexdef
        FROM pg_indexes 
        WHERE schemaname = 'public' 
        AND tablename IN (
            'student_details', 
            'teacher_section_subject', 
            'attendances', 
            'student_qr_codes', 
            'guardhouse_attendance',
            'attendance_sessions',
            'attendance_records',
            'sections',
            'student_section',
            'subjects',
            'teachers',
            'subject_schedules'
        )
        ORDER BY tablename, indexname
    ");
    
    $currentTable = '';
    $indexCount = 0;
    $existingIndexes = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($currentTable !== $row['tablename']) {
            $currentTable = $row['tablename'];
            echo "📊 Table: {$currentTable}\n";
        }
        echo "   • {$row['indexname']}\n";
        $indexCount++;
        
        // Store existing indexes
        $existingIndexes[] = $row['indexname'];
    }
    
    echo "\n✅ Total indexes found: $indexCount\n\n";
    
    // Check which of our planned indexes already exist
    $plannedIndexes = [
        'idx_student_details_student_id',
        'idx_student_details_grade_section',
        'idx_student_details_active',
        'idx_tss_teacher_id',
        'idx_tss_section_id',
        'idx_tss_subject_id',
        'idx_attendances_date',
        'idx_attendances_student_id',
        'idx_student_qr_codes_data',
        'idx_student_qr_codes_active',
        'idx_guardhouse_attendance_student',
        'idx_guardhouse_attendance_date',
        'idx_guardhouse_attendance_timestamp'
    ];
    
    echo "🎯 Index Status Check:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $existingCount = 0;
    $missingCount = 0;
    
    foreach ($plannedIndexes as $index) {
        if (in_array($index, $existingIndexes)) {
            echo "✅ $index (EXISTS)\n";
            $existingCount++;
        } else {
            echo "❌ $index (MISSING)\n";
            $missingCount++;
        }
    }
    
    echo "\n📈 Summary:\n";
    echo "   Existing indexes: $existingCount\n";
    echo "   Missing indexes: $missingCount\n";
    echo "   Total planned: " . count($plannedIndexes) . "\n\n";
    
    if ($missingCount > 0) {
        echo "💡 Recommendation: Create the missing indexes for better performance!\n";
    } else {
        echo "🎉 Great! All critical indexes are already in place!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error connecting to database: " . $e->getMessage() . "\n";
    echo "💡 Make sure PostgreSQL is running and credentials are correct.\n";
}
