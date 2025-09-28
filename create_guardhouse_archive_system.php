<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CREATING GUARDHOUSE ARCHIVE SYSTEM ===\n";
    
    // 1. Create archive table
    echo "1. Creating guardhouse_attendance_archive table...\n";
    $createArchiveSQL = "
        CREATE TABLE IF NOT EXISTS guardhouse_attendance_archive (
            id SERIAL PRIMARY KEY,
            original_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            qr_code_data VARCHAR(255) NOT NULL,
            record_type VARCHAR(20) NOT NULL CHECK (record_type IN ('check-in', 'check-out')),
            timestamp TIMESTAMP NOT NULL,
            date DATE NOT NULL,
            guard_name VARCHAR(100) DEFAULT 'Bread Doe',
            guard_id VARCHAR(20) DEFAULT 'G-12345',
            is_manual BOOLEAN DEFAULT FALSE,
            notes TEXT,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_archive_student_id FOREIGN KEY (student_id) REFERENCES student_details(id) ON DELETE CASCADE
        );
    ";
    $pdo->exec($createArchiveSQL);
    echo "✅ Archive table created successfully\n";
    
    // 2. Create indexes for archive table
    echo "2. Creating indexes for archive table...\n";
    $indexSQL = [
        "CREATE INDEX IF NOT EXISTS idx_archive_student_id ON guardhouse_attendance_archive(student_id);",
        "CREATE INDEX IF NOT EXISTS idx_archive_date ON guardhouse_attendance_archive(date);",
        "CREATE INDEX IF NOT EXISTS idx_archive_record_type ON guardhouse_attendance_archive(record_type);",
        "CREATE INDEX IF NOT EXISTS idx_archive_timestamp ON guardhouse_attendance_archive(timestamp);",
        "CREATE INDEX IF NOT EXISTS idx_archive_archived_at ON guardhouse_attendance_archive(archived_at);"
    ];
    
    foreach ($indexSQL as $sql) {
        $pdo->exec($sql);
    }
    echo "✅ Archive indexes created successfully\n";
    
    // 3. Create cache table for quick access to recent data
    echo "3. Creating guardhouse_attendance_cache table...\n";
    $createCacheSQL = "
        CREATE TABLE IF NOT EXISTS guardhouse_attendance_cache (
            id SERIAL PRIMARY KEY,
            cache_date DATE NOT NULL UNIQUE,
            total_checkins INTEGER DEFAULT 0,
            total_checkouts INTEGER DEFAULT 0,
            peak_hour_checkins VARCHAR(10),
            peak_hour_checkouts VARCHAR(10),
            records_data JSONB,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createCacheSQL);
    echo "✅ Cache table created successfully\n";
    
    // 4. Create function to archive old records
    echo "4. Creating archive function...\n";
    $archiveFunctionSQL = "
        CREATE OR REPLACE FUNCTION archive_old_guardhouse_records()
        RETURNS INTEGER AS \$\$
        DECLARE
            archived_count INTEGER := 0;
            cutoff_date DATE := CURRENT_DATE - INTERVAL '1 day';
        BEGIN
            -- Move records older than 1 day to archive
            INSERT INTO guardhouse_attendance_archive (
                original_id, student_id, qr_code_data, record_type, timestamp, date,
                guard_name, guard_id, is_manual, notes, created_at, updated_at
            )
            SELECT 
                id, student_id, qr_code_data, record_type, timestamp, date,
                guard_name, guard_id, is_manual, notes, created_at, updated_at
            FROM guardhouse_attendance
            WHERE date < cutoff_date;
            
            GET DIAGNOSTICS archived_count = ROW_COUNT;
            
            -- Delete archived records from main table
            DELETE FROM guardhouse_attendance WHERE date < cutoff_date;
            
            -- Update cache for archived dates
            INSERT INTO guardhouse_attendance_cache (cache_date, total_checkins, total_checkouts, records_data)
            SELECT 
                date,
                COUNT(CASE WHEN record_type = 'check-in' THEN 1 END) as total_checkins,
                COUNT(CASE WHEN record_type = 'check-out' THEN 1 END) as total_checkouts,
                jsonb_agg(
                    jsonb_build_object(
                        'id', original_id,
                        'student_id', student_id,
                        'record_type', record_type,
                        'timestamp', timestamp,
                        'guard_name', guard_name
                    )
                ) as records_data
            FROM guardhouse_attendance_archive 
            WHERE date >= CURRENT_DATE - INTERVAL '90 days'
            GROUP BY date
            ON CONFLICT (cache_date) DO UPDATE SET
                total_checkins = EXCLUDED.total_checkins,
                total_checkouts = EXCLUDED.total_checkouts,
                records_data = EXCLUDED.records_data,
                last_updated = CURRENT_TIMESTAMP;
            
            RETURN archived_count;
        END;
        \$\$ LANGUAGE plpgsql;
    ";
    $pdo->exec($archiveFunctionSQL);
    echo "✅ Archive function created successfully\n";
    
    // 5. Create function to clean old archive data (older than 90 days)
    echo "5. Creating cleanup function...\n";
    $cleanupFunctionSQL = "
        CREATE OR REPLACE FUNCTION cleanup_old_archive_records()
        RETURNS INTEGER AS \$\$
        DECLARE
            deleted_count INTEGER := 0;
            cutoff_date DATE := CURRENT_DATE - INTERVAL '90 days';
        BEGIN
            -- Delete archive records older than 90 days
            DELETE FROM guardhouse_attendance_archive WHERE date < cutoff_date;
            GET DIAGNOSTICS deleted_count = ROW_COUNT;
            
            -- Delete cache records older than 90 days
            DELETE FROM guardhouse_attendance_cache WHERE cache_date < cutoff_date;
            
            RETURN deleted_count;
        END;
        \$\$ LANGUAGE plpgsql;
    ";
    $pdo->exec($cleanupFunctionSQL);
    echo "✅ Cleanup function created successfully\n";
    
    // 6. Test the archive function with any existing old records
    echo "6. Testing archive function...\n";
    $testResult = $pdo->query("SELECT archive_old_guardhouse_records() as archived_count");
    $archivedCount = $testResult->fetchColumn();
    echo "✅ Archived $archivedCount old records\n";
    
    // 7. Show current status
    echo "\n=== SYSTEM STATUS ===\n";
    
    // Count current records
    $currentCount = $pdo->query("SELECT COUNT(*) FROM guardhouse_attendance")->fetchColumn();
    echo "Current records (today): $currentCount\n";
    
    // Count archived records
    $archiveCount = $pdo->query("SELECT COUNT(*) FROM guardhouse_attendance_archive")->fetchColumn();
    echo "Archived records: $archiveCount\n";
    
    // Count cache entries
    $cacheCount = $pdo->query("SELECT COUNT(*) FROM guardhouse_attendance_cache")->fetchColumn();
    echo "Cache entries: $cacheCount\n";
    
    echo "\n✅ GUARDHOUSE ARCHIVE SYSTEM SETUP COMPLETE!\n";
    echo "📋 Next steps:\n";
    echo "   - Set up daily cron job to run archive_old_guardhouse_records()\n";
    echo "   - Set up weekly cron job to run cleanup_old_archive_records()\n";
    echo "   - Implement admin interface for historical data access\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
