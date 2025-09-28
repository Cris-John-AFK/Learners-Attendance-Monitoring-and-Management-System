<?php
/**
 * Daily Archive Job for Guardhouse Attendance
 * 
 * This script should be run daily via cron job to:
 * 1. Archive records older than 1 day
 * 2. Update cache for quick access
 * 3. Clean up records older than 90 days
 * 
 * Cron job example (run at 2 AM daily):
 * 0 2 * * * /usr/bin/php /path/to/daily_archive_job.php
 */

try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=lamms_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $logFile = __DIR__ . '/logs/archive_job_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    function logMessage($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        echo "[$timestamp] $message\n";
    }
    
    logMessage("=== DAILY ARCHIVE JOB STARTED ===");
    
    // 1. Archive old records
    logMessage("Archiving records older than 1 day...");
    $archiveResult = $pdo->query("SELECT archive_old_guardhouse_records() as archived_count");
    $archivedCount = $archiveResult->fetchColumn();
    logMessage("✅ Archived $archivedCount records");
    
    // 2. Clean up very old records (older than 90 days)
    logMessage("Cleaning up records older than 90 days...");
    $cleanupResult = $pdo->query("SELECT cleanup_old_archive_records() as deleted_count");
    $deletedCount = $cleanupResult->fetchColumn();
    logMessage("✅ Deleted $deletedCount old archive records");
    
    // 3. Update statistics
    logMessage("Updating system statistics...");
    
    // Current records count
    $currentCount = $pdo->query("SELECT COUNT(*) FROM guardhouse_attendance")->fetchColumn();
    
    // Archive records count
    $archiveCount = $pdo->query("SELECT COUNT(*) FROM guardhouse_attendance_archive")->fetchColumn();
    
    // Cache entries count
    $cacheCount = $pdo->query("SELECT COUNT(*) FROM guardhouse_attendance_cache")->fetchColumn();
    
    logMessage("📊 System Status:");
    logMessage("   - Current records (today): $currentCount");
    logMessage("   - Archived records: $archiveCount");
    logMessage("   - Cache entries: $cacheCount");
    
    // 4. Optimize database tables
    logMessage("Optimizing database tables...");
    $pdo->exec("VACUUM ANALYZE guardhouse_attendance");
    $pdo->exec("VACUUM ANALYZE guardhouse_attendance_archive");
    $pdo->exec("VACUUM ANALYZE guardhouse_attendance_cache");
    logMessage("✅ Database optimization complete");
    
    logMessage("=== DAILY ARCHIVE JOB COMPLETED SUCCESSFULLY ===");
    
} catch (Exception $e) {
    $errorMessage = "❌ Archive job failed: " . $e->getMessage();
    logMessage($errorMessage);
    
    // Send email notification on failure (optional)
    // mail('admin@school.com', 'Archive Job Failed', $errorMessage);
    
    exit(1);
}
?>
