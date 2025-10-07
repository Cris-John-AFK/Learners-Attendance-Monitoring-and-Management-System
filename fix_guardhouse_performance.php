<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Fixing guardhouse performance issues...\n";
    
    // Check if archive tables exist
    $archiveSessionsExists = DB::select("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'guardhouse_archive_sessions')")[0]->exists;
    $archivedRecordsExists = DB::select("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'guardhouse_archived_records')")[0]->exists;
    
    if (!$archiveSessionsExists || !$archivedRecordsExists) {
        echo "⚠️  Archive tables missing, creating them...\n";
        
        // Create guardhouse_archive_sessions table
        if (!$archiveSessionsExists) {
            DB::statement("
                CREATE TABLE guardhouse_archive_sessions (
                    id SERIAL PRIMARY KEY,
                    session_date DATE NOT NULL,
                    total_records INTEGER DEFAULT 0,
                    archived_at TIMESTAMP NOT NULL,
                    archived_by BIGINT,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
            
            // Add indexes
            DB::statement("CREATE INDEX idx_archive_sessions_date ON guardhouse_archive_sessions(session_date)");
            DB::statement("CREATE INDEX idx_archive_sessions_archived_at ON guardhouse_archive_sessions(archived_at)");
            
            echo "✅ Created guardhouse_archive_sessions table\n";
        }
        
        // Create guardhouse_archived_records table
        if (!$archivedRecordsExists) {
            DB::statement("
                CREATE TABLE guardhouse_archived_records (
                    id SERIAL PRIMARY KEY,
                    session_id BIGINT,
                    student_id VARCHAR(255),
                    student_name VARCHAR(255) NOT NULL,
                    grade_level VARCHAR(255),
                    section VARCHAR(255),
                    record_type VARCHAR(20) CHECK (record_type IN ('check-in', 'check-out')),
                    timestamp TIMESTAMP NOT NULL,
                    session_date DATE NOT NULL,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
            
            // Add indexes for performance
            DB::statement("CREATE INDEX idx_archived_records_session ON guardhouse_archived_records(session_id)");
            DB::statement("CREATE INDEX idx_archived_records_student_id ON guardhouse_archived_records(student_id)");
            DB::statement("CREATE INDEX idx_archived_records_student_name ON guardhouse_archived_records(student_name)");
            DB::statement("CREATE INDEX idx_archived_records_session_date ON guardhouse_archived_records(session_date)");
            DB::statement("CREATE INDEX idx_archived_records_record_type ON guardhouse_archived_records(record_type)");
            
            echo "✅ Created guardhouse_archived_records table\n";
        }
    } else {
        echo "✅ Archive tables already exist\n";
    }
    
    // Check current guardhouse_attendance table performance
    $currentRecords = DB::table('guardhouse_attendance')->count();
    echo "📊 Current live records: {$currentRecords}\n";
    
    if ($currentRecords > 1000) {
        echo "⚠️  Large number of live records detected. Consider archiving old records.\n";
    }
    
    // Add performance indexes to guardhouse_attendance if they don't exist
    $indexes = [
        'idx_guardhouse_attendance_date' => 'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_date ON guardhouse_attendance(date)',
        'idx_guardhouse_attendance_student_id' => 'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_student_id ON guardhouse_attendance(student_id)',
        'idx_guardhouse_attendance_record_type' => 'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_record_type ON guardhouse_attendance(record_type)',
        'idx_guardhouse_attendance_timestamp' => 'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_timestamp ON guardhouse_attendance(timestamp)',
        'idx_guardhouse_attendance_date_type' => 'CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_date_type ON guardhouse_attendance(date, record_type)'
    ];
    
    foreach ($indexes as $name => $sql) {
        try {
            DB::statement($sql);
            echo "✅ Added index: {$name}\n";
        } catch (Exception $e) {
            echo "ℹ️  Index {$name} already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // Optimize the guardhouse_attendance table
    DB::statement('VACUUM ANALYZE guardhouse_attendance');
    echo "✅ Optimized guardhouse_attendance table\n";
    
    echo "\n🎉 Performance optimization complete!\n";
    echo "The guardhouse reports should now load much faster.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
