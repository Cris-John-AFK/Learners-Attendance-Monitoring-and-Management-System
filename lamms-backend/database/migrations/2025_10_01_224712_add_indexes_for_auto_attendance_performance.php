<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Index for checking existing sessions (auto-attendance lookup)
            $table->index(['teacher_id', 'section_id', 'subject_id', 'session_date', 'status'], 'idx_session_lookup');
            
            // Index for session date queries
            $table->index('session_date', 'idx_session_date');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            // Index for checking existing attendance (prevents duplicates)
            $table->index('attendance_session_id', 'idx_attendance_session');
            
            // Composite index for student attendance lookup
            $table->index(['attendance_session_id', 'student_id'], 'idx_session_student_attendance');
        });

        Schema::table('notifications', function (Blueprint $table) {
            // Index for loading teacher notifications
            $table->index(['user_id', 'created_at'], 'idx_user_notifications');
            
            // Index for notification type queries
            $table->index('type', 'idx_notification_type');
        });

        Schema::table('student_section', function (Blueprint $table) {
            // Index for active students in section lookup
            $table->index(['section_id', 'is_active'], 'idx_section_active_students');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_session_lookup');
            $table->dropIndex('idx_session_date');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_session');
            $table->dropIndex('idx_session_student_attendance');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_user_notifications');
            $table->dropIndex('idx_notification_type');
        });

        Schema::table('student_section', function (Blueprint $table) {
            $table->dropIndex('idx_section_active_students');
        });
    }
};
