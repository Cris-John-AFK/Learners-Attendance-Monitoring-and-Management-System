<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for performance optimization
     */
    public function up(): void
    {
        // Critical indexes for dashboard performance
        try {
            Schema::table('student_details', function (Blueprint $table) {
                $table->index(['current_status', 'isActive'], 'idx_student_status_active');
                $table->index(['id', 'current_status'], 'idx_student_id_status');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        try {
            Schema::table('student_section', function (Blueprint $table) {
                $table->index(['student_id', 'section_id', 'is_active'], 'idx_student_section_active');
                $table->index(['section_id', 'is_active'], 'idx_section_active');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        try {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index(['teacher_id', 'is_active'], 'idx_teacher_active');
                $table->index(['teacher_id', 'section_id', 'is_active'], 'idx_teacher_section_active');
                $table->index(['teacher_id', 'subject_id', 'is_active'], 'idx_teacher_subject_active');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        try {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index(['student_id', 'date'], 'idx_student_date');
                $table->index(['teacher_id', 'date'], 'idx_teacher_date');
                $table->index(['section_id', 'subject_id', 'date'], 'idx_section_subject_date');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        try {
            // Add indexes for attendance_records table if it exists
            if (Schema::hasTable('attendance_records')) {
                Schema::table('attendance_records', function (Blueprint $table) {
                    $table->index(['student_id', 'attendance_session_id'], 'idx_student_session');
                    $table->index(['is_current_version', 'student_id'], 'idx_current_student');
                });
            }
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        try {
            // Add indexes for attendance_sessions table if it exists
            if (Schema::hasTable('attendance_sessions')) {
                Schema::table('attendance_sessions', function (Blueprint $table) {
                    $table->index(['teacher_id', 'subject_id', 'section_id'], 'idx_teacher_subject_section');
                    $table->index(['session_date', 'subject_id'], 'idx_date_subject');
                    $table->index(['teacher_id', 'session_date'], 'idx_teacher_session_date');
                });
            }
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        try {
            Schema::table('sections', function (Blueprint $table) {
                $table->index(['id', 'is_active'], 'idx_section_id_active');
                $table->index(['homeroom_teacher_id'], 'idx_homeroom_teacher');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_student_session');
            $table->dropIndex('idx_student_date');
            $table->dropIndex('idx_current_student');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_teacher_subject_section');
            $table->dropIndex('idx_date_subject');
            $table->dropIndex('idx_status_date');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_student_status');
        });

        if (Schema::hasTable('teacher_subject_section_assignments')) {
            Schema::table('teacher_subject_section_assignments', function (Blueprint $table) {
                $table->dropIndex('idx_teacher_assignment');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('idx_teacher_notifications');
            });
        }
    }
};
