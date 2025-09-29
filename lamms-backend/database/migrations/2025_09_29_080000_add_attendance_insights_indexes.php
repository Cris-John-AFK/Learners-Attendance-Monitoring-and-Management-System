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
        // Add indexes for attendance insights performance
        
        // Composite index for teacher section subject filtering
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            $table->index(['teacher_id', 'subject_id', 'is_active'], 'idx_tss_teacher_subject_active');
            $table->index(['section_id', 'subject_id', 'is_active'], 'idx_tss_section_subject_active');
        });

        // Composite index for student section filtering
        Schema::table('student_section', function (Blueprint $table) {
            $table->index(['section_id', 'is_active'], 'idx_ss_section_active');
            $table->index(['student_id', 'is_active'], 'idx_ss_student_active');
        });

        // Composite index for attendance records with session
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['student_id', 'attendance_session_id'], 'idx_ar_student_session');
            $table->index(['attendance_session_id', 'attendance_status_id'], 'idx_ar_session_status');
        });

        // Composite index for attendance sessions by date
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->index(['session_date', 'id'], 'idx_as_date_id');
        });

        // Index for student details status
        Schema::table('student_details', function (Blueprint $table) {
            $table->index(['current_status'], 'idx_sd_status');
        });

        // Index for attendance statuses code
        Schema::table('attendance_statuses', function (Blueprint $table) {
            $table->index(['code'], 'idx_ast_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the indexes
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            $table->dropIndex('idx_tss_teacher_subject_active');
            $table->dropIndex('idx_tss_section_subject_active');
        });

        Schema::table('student_section', function (Blueprint $table) {
            $table->dropIndex('idx_ss_section_active');
            $table->dropIndex('idx_ss_student_active');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_ar_student_session');
            $table->dropIndex('idx_ar_session_status');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_as_date_id');
        });

        Schema::table('student_details', function (Blueprint $table) {
            $table->dropIndex('idx_sd_status');
        });

        Schema::table('attendance_statuses', function (Blueprint $table) {
            $table->dropIndex('idx_ast_code');
        });
    }
};
