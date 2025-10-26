<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations for performance optimization indexes
     * These indexes will dramatically improve query performance across the LAMMS system
     */
    public function up()
    {
        // 1. STUDENT_DETAILS TABLE INDEXES
        // Primary lookup indexes for student operations
        Schema::table('student_details', function (Blueprint $table) {
            // Fast student ID lookups (used in QR codes and attendance)
            $table->index('student_id', 'idx_student_details_student_id');
            
            // Grade and section filtering (used in admin dashboards)
            $table->index(['gradeLevel', 'section'], 'idx_student_details_grade_section');
            
            // Active student filtering
            $table->index('isActive', 'idx_student_details_active');
            
            // Name searches (used in student management)
            $table->index(['firstName', 'lastName'], 'idx_student_details_names');
            
            // LRN lookups (official student identification)
            $table->index('lrn', 'idx_student_details_lrn');
        });

        // 2. TEACHER_SECTION_SUBJECT TABLE INDEXES
        // Critical pivot table - heavily queried for assignments
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            // Teacher assignment lookups (most common query)
            $table->index('teacher_id', 'idx_tss_teacher_id');
            
            // Section-based queries (used in section management)
            $table->index('section_id', 'idx_tss_section_id');
            
            // Subject-based queries
            $table->index('subject_id', 'idx_tss_subject_id');
            
            // Active assignments only
            $table->index('is_active', 'idx_tss_active');
            
            // Composite index for teacher-section-subject lookups
            $table->index(['teacher_id', 'section_id', 'subject_id'], 'idx_tss_composite');
            
            // Homeroom teacher queries
            $table->index(['teacher_id', 'role'], 'idx_tss_teacher_role');
        });

        // 3. ATTENDANCES TABLE INDEXES
        // High-volume table with frequent reads/writes
        Schema::table('attendances', function (Blueprint $table) {
            // Date-based queries (most common for reports)
            $table->index('date', 'idx_attendances_date');
            
            // Student attendance history
            $table->index('student_id', 'idx_attendances_student_id');
            
            // Teacher attendance marking
            $table->index('teacher_id', 'idx_attendances_teacher_id');
            
            // Section-based attendance
            $table->index('section_id', 'idx_attendances_section_id');
            
            // Subject-based attendance
            $table->index('subject_id', 'idx_attendances_subject_id');
            
            // Status filtering (present/absent/late/excused)
            $table->index('status', 'idx_attendances_status');
            
            // Composite index for common queries
            $table->index(['student_id', 'date'], 'idx_attendances_student_date');
            $table->index(['section_id', 'date'], 'idx_attendances_section_date');
            $table->index(['teacher_id', 'date'], 'idx_attendances_teacher_date');
        });

        // 4. ATTENDANCE_SESSIONS TABLE INDEXES
        // Production attendance system
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Session date queries
            $table->index('session_date', 'idx_attendance_sessions_date');
            
            // Teacher session lookups
            $table->index('teacher_id', 'idx_attendance_sessions_teacher');
            
            // Section session lookups
            $table->index('section_id', 'idx_attendance_sessions_section');
            
            // Subject session lookups
            $table->index('subject_id', 'idx_attendance_sessions_subject');
            
            // Session status filtering
            $table->index('status', 'idx_attendance_sessions_status');
            
            // Composite index for teacher-section-subject sessions
            $table->index(['teacher_id', 'section_id', 'subject_id'], 'idx_attendance_sessions_composite');
        });

        // 5. ATTENDANCE_RECORDS TABLE INDEXES
        // Detailed attendance records
        Schema::table('attendance_records', function (Blueprint $table) {
            // Session-based lookups
            $table->index('attendance_session_id', 'idx_attendance_records_session');
            
            // Student record lookups
            $table->index('student_id', 'idx_attendance_records_student');
            
            // Status-based filtering
            $table->index('attendance_status_id', 'idx_attendance_records_status');
            
            // Composite index for session-student lookups
            $table->index(['attendance_session_id', 'student_id'], 'idx_attendance_records_session_student');
        });

        // 6. STUDENT_QR_CODES TABLE INDEXES
        // Critical for QR scanning performance
        Schema::table('student_qr_codes', function (Blueprint $table) {
            // QR code data lookups (most critical - used in real-time scanning)
            $table->index('qr_code_data', 'idx_student_qr_codes_data');
            
            // Student QR code lookups
            $table->index('student_id', 'idx_student_qr_codes_student');
            
            // Active QR codes only
            $table->index('is_active', 'idx_student_qr_codes_active');
            
            // Composite index for active QR code lookups
            $table->index(['qr_code_data', 'is_active'], 'idx_student_qr_codes_data_active');
        });

        // 7. GUARDHOUSE_ATTENDANCE TABLE INDEXES
        // Real-time QR scanning operations
        if (Schema::hasTable('guardhouse_attendance')) {
            Schema::table('guardhouse_attendance', function (Blueprint $table) {
                // Student attendance lookups
                $table->index('student_id', 'idx_guardhouse_attendance_student');
                
                // Date-based queries (today's records)
                $table->index('date', 'idx_guardhouse_attendance_date');
                
                // Record type filtering (check-in/check-out)
                $table->index('record_type', 'idx_guardhouse_attendance_type');
                
                // QR code lookups
                $table->index('qr_code_data', 'idx_guardhouse_attendance_qr');
                
                // Timestamp ordering
                $table->index('timestamp', 'idx_guardhouse_attendance_timestamp');
                
                // Composite indexes for common queries
                $table->index(['student_id', 'date'], 'idx_guardhouse_attendance_student_date');
                $table->index(['date', 'record_type'], 'idx_guardhouse_attendance_date_type');
            });
        }

        // 8. SECTIONS TABLE INDEXES
        // Section management and filtering
        Schema::table('sections', function (Blueprint $table) {
            // Curriculum grade lookups
            $table->index('curriculum_grade_id', 'idx_sections_curriculum_grade');
            
            // Homeroom teacher lookups
            $table->index('homeroom_teacher_id', 'idx_sections_homeroom_teacher');
            
            // Active sections filtering
            $table->index('is_active', 'idx_sections_active');
            
            // Section name searches
            $table->index('name', 'idx_sections_name');
        });

        // 9. STUDENT_SECTION TABLE INDEXES
        // Student enrollment relationships
        Schema::table('student_section', function (Blueprint $table) {
            // Student enrollment lookups
            $table->index('student_id', 'idx_student_section_student');
            
            // Section enrollment lookups
            $table->index('section_id', 'idx_student_section_section');
            
            // Active enrollments only
            $table->index('is_active', 'idx_student_section_active');
            
            // Composite index for active student-section relationships
            $table->index(['student_id', 'section_id', 'is_active'], 'idx_student_section_composite');
        });

        // 10. SUBJECTS TABLE INDEXES
        Schema::table('subjects', function (Blueprint $table) {
            // Subject name searches
            $table->index('name', 'idx_subjects_name');
            
            // Active subjects filtering
            $table->index('is_active', 'idx_subjects_active');
        });

        // 11. TEACHERS TABLE INDEXES
        Schema::table('teachers', function (Blueprint $table) {
            // User relationship lookups
            $table->index('user_id', 'idx_teachers_user_id');
            
            // Name searches
            $table->index(['first_name', 'last_name'], 'idx_teachers_names');
            
            // Head teacher filtering
            $table->index('is_head_teacher', 'idx_teachers_head_teacher');
        });

        // 12. SUBJECT_SCHEDULES TABLE INDEXES
        Schema::table('subject_schedules', function (Blueprint $table) {
            // Section schedule lookups
            $table->index('section_id', 'idx_subject_schedules_section');
            
            // Subject schedule lookups
            $table->index('subject_id', 'idx_subject_schedules_subject');
            
            // Teacher schedule lookups
            $table->index('teacher_id', 'idx_subject_schedules_teacher');
            
            // Day of week filtering
            $table->index('day', 'idx_subject_schedules_day');
            
            // Composite index for section-subject schedules
            $table->index(['section_id', 'subject_id'], 'idx_subject_schedules_section_subject');
        });

        // Add any additional indexes based on your specific query patterns
        echo "✅ Performance indexes created successfully!\n";
        echo "📊 Your LAMMS system should now be significantly faster!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Drop all the indexes we created
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropIndex('idx_student_details_student_id');
            $table->dropIndex('idx_student_details_grade_section');
            $table->dropIndex('idx_student_details_active');
            $table->dropIndex('idx_student_details_names');
            $table->dropIndex('idx_student_details_lrn');
        });

        Schema::table('teacher_section_subject', function (Blueprint $table) {
            $table->dropIndex('idx_tss_teacher_id');
            $table->dropIndex('idx_tss_section_id');
            $table->dropIndex('idx_tss_subject_id');
            $table->dropIndex('idx_tss_active');
            $table->dropIndex('idx_tss_composite');
            $table->dropIndex('idx_tss_teacher_role');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_date');
            $table->dropIndex('idx_attendances_student_id');
            $table->dropIndex('idx_attendances_teacher_id');
            $table->dropIndex('idx_attendances_section_id');
            $table->dropIndex('idx_attendances_subject_id');
            $table->dropIndex('idx_attendances_status');
            $table->dropIndex('idx_attendances_student_date');
            $table->dropIndex('idx_attendances_section_date');
            $table->dropIndex('idx_attendances_teacher_date');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_sessions_date');
            $table->dropIndex('idx_attendance_sessions_teacher');
            $table->dropIndex('idx_attendance_sessions_section');
            $table->dropIndex('idx_attendance_sessions_subject');
            $table->dropIndex('idx_attendance_sessions_status');
            $table->dropIndex('idx_attendance_sessions_composite');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_records_session');
            $table->dropIndex('idx_attendance_records_student');
            $table->dropIndex('idx_attendance_records_status');
            $table->dropIndex('idx_attendance_records_session_student');
        });

        Schema::table('student_qr_codes', function (Blueprint $table) {
            $table->dropIndex('idx_student_qr_codes_data');
            $table->dropIndex('idx_student_qr_codes_student');
            $table->dropIndex('idx_student_qr_codes_active');
            $table->dropIndex('idx_student_qr_codes_data_active');
        });

        Schema::table('guardhouse_attendance', function (Blueprint $table) {
            $table->dropIndex('idx_guardhouse_attendance_student');
            $table->dropIndex('idx_guardhouse_attendance_date');
            $table->dropIndex('idx_guardhouse_attendance_type');
            $table->dropIndex('idx_guardhouse_attendance_qr');
            $table->dropIndex('idx_guardhouse_attendance_timestamp');
            $table->dropIndex('idx_guardhouse_attendance_student_date');
            $table->dropIndex('idx_guardhouse_attendance_date_type');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex('idx_sections_curriculum_grade');
            $table->dropIndex('idx_sections_homeroom_teacher');
            $table->dropIndex('idx_sections_active');
            $table->dropIndex('idx_sections_name');
        });

        Schema::table('student_section', function (Blueprint $table) {
            $table->dropIndex('idx_student_section_student');
            $table->dropIndex('idx_student_section_section');
            $table->dropIndex('idx_student_section_active');
            $table->dropIndex('idx_student_section_composite');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex('idx_subjects_name');
            $table->dropIndex('idx_subjects_active');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('idx_teachers_user_id');
            $table->dropIndex('idx_teachers_names');
            $table->dropIndex('idx_teachers_head_teacher');
        });

        Schema::table('subject_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_subject_schedules_section');
            $table->dropIndex('idx_subject_schedules_subject');
            $table->dropIndex('idx_subject_schedules_teacher');
            $table->dropIndex('idx_subject_schedules_day');
            $table->dropIndex('idx_subject_schedules_section_subject');
        });
    }
};
