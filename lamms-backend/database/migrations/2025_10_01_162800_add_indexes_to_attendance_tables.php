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
            // Add indexes for frequently queried columns
            $table->index('teacher_id', 'idx_attendance_sessions_teacher_id');
            $table->index('section_id', 'idx_attendance_sessions_section_id');
            $table->index('subject_id', 'idx_attendance_sessions_subject_id');
            $table->index('session_date', 'idx_attendance_sessions_session_date');
            $table->index('status', 'idx_attendance_sessions_status');
            
            // Composite indexes for common query patterns
            $table->index(['teacher_id', 'session_date', 'status'], 'idx_attendance_sessions_teacher_date_status');
            $table->index(['section_id', 'session_date'], 'idx_attendance_sessions_section_date');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            $table->index('attendance_session_id', 'idx_attendance_records_session_id');
            $table->index('student_id', 'idx_attendance_records_student_id');
            $table->index('attendance_status_id', 'idx_attendance_records_status_id');
            $table->index('marked_by_teacher_id', 'idx_attendance_records_marked_by');
            $table->index('reason_id', 'idx_attendance_records_reason_id');
            $table->index('marked_at', 'idx_attendance_records_marked_at');
            
            // Composite index for session + student lookups
            $table->index(['attendance_session_id', 'student_id'], 'idx_attendance_records_session_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_sessions_teacher_id');
            $table->dropIndex('idx_attendance_sessions_section_id');
            $table->dropIndex('idx_attendance_sessions_subject_id');
            $table->dropIndex('idx_attendance_sessions_session_date');
            $table->dropIndex('idx_attendance_sessions_status');
            $table->dropIndex('idx_attendance_sessions_teacher_date_status');
            $table->dropIndex('idx_attendance_sessions_section_date');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_records_session_id');
            $table->dropIndex('idx_attendance_records_student_id');
            $table->dropIndex('idx_attendance_records_status_id');
            $table->dropIndex('idx_attendance_records_marked_by');
            $table->dropIndex('idx_attendance_records_reason_id');
            $table->dropIndex('idx_attendance_records_marked_at');
            $table->dropIndex('idx_attendance_records_session_student');
        });
    }
};
