<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Critical indexes for dashboard performance based on network analysis
        
        try {
            // 1. AttendanceAnalyticsCache indexes (for critical-absenteeism endpoint)
            if (Schema::hasTable('attendance_analytics_cache')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_analytics_cache_critical ON attendance_analytics_cache (exceeds_18_absence_limit, analysis_date, total_absences_this_year)');
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_analytics_cache_student ON attendance_analytics_cache (student_id, analysis_date)');
                echo "✅ Created AttendanceAnalyticsCache indexes\n";
            }
        } catch (\Exception $e) {
            echo "⚠️ AttendanceAnalyticsCache indexes: " . $e->getMessage() . "\n";
        }

        try {
            // 2. Teacher-Section-Subject indexes (for teacher filtering)
            if (Schema::hasTable('teacher_section_subject')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tss_teacher_active ON teacher_section_subject (teacher_id, is_active)');
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tss_section_active ON teacher_section_subject (section_id, is_active)');
                echo "✅ Created teacher_section_subject indexes\n";
            }
        } catch (\Exception $e) {
            echo "⚠️ teacher_section_subject indexes: " . $e->getMessage() . "\n";
        }

        try {
            // 3. Student-Section indexes (for student filtering)
            if (Schema::hasTable('student_section')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_student_section_active ON student_section (section_id, is_active)');
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_student_section_student ON student_section (student_id, is_active)');
                echo "✅ Created student_section indexes\n";
            }
        } catch (\Exception $e) {
            echo "⚠️ student_section indexes: " . $e->getMessage() . "\n";
        }

        try {
            // 4. Attendances table indexes (for summary queries)
            if (Schema::hasTable('attendances')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_attendances_teacher_date ON attendances (teacher_id, date)');
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_attendances_student_date ON attendances (student_id, date)');
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_attendances_section_date ON attendances (section_id, date)');
                echo "✅ Created attendances indexes\n";
            }
        } catch (\Exception $e) {
            echo "⚠️ attendances indexes: " . $e->getMessage() . "\n";
        }

        try {
            // 5. Notifications table indexes (for notification queries)
            if (Schema::hasTable('notifications')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_user_created ON notifications (user_id, created_at)');
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_user_read ON notifications (user_id, is_read)');
                echo "✅ Created notifications indexes\n";
            }
        } catch (\Exception $e) {
            echo "⚠️ notifications indexes: " . $e->getMessage() . "\n";
        }

        echo "🎉 Critical performance indexes migration completed!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes if needed
        $indexes = [
            'idx_analytics_cache_critical',
            'idx_analytics_cache_student',
            'idx_tss_teacher_active',
            'idx_tss_section_active',
            'idx_student_section_active',
            'idx_student_section_student',
            'idx_attendances_teacher_date',
            'idx_attendances_student_date',
            'idx_attendances_section_date',
            'idx_notifications_user_created',
            'idx_notifications_user_read'
        ];

        foreach ($indexes as $index) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$index}");
            } catch (\Exception $e) {
                // Continue if index doesn't exist
            }
        }
    }
};
