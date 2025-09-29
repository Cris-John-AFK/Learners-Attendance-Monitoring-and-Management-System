<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - TEACHER ATTENDANCE PERFORMANCE INDEXES
     */
    public function up()
    {
        // Critical indexes for teacher attendance filtering
        
        // 1. Attendance sessions - teacher filtering (CRITICAL)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_teacher_date 
                      ON attendance_sessions (teacher_id, session_date) 
                      WHERE teacher_id IS NOT NULL');
        
        // 2. Attendance sessions - subject filtering for teacher sessions
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_teacher_subject 
                      ON attendance_sessions (teacher_id, subject_id, session_date) 
                      WHERE teacher_id IS NOT NULL');
        
        // 3. Teacher section subject - active assignments
        DB::statement('CREATE INDEX IF NOT EXISTS idx_teacher_section_subject_active 
                      ON teacher_section_subject (teacher_id, is_active, subject_id) 
                      WHERE is_active = true');
        
        // 4. Student section - active enrollments
        DB::statement('CREATE INDEX IF NOT EXISTS idx_student_section_active 
                      ON student_section (section_id, is_active, student_id) 
                      WHERE is_active = true');
        
        // 5. Student details - active status
        DB::statement('CREATE INDEX IF NOT EXISTS idx_student_details_status 
                      ON student_details (current_status, id) 
                      WHERE current_status = \'active\'');
        
        // 6. Attendance records - session and student lookup
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_session_student 
                      ON attendance_records (attendance_session_id, student_id, attendance_status_id)');
        
        // 7. Composite index for attendance trends query (PostgreSQL specific)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_trends_composite 
                      ON attendance_records (attendance_session_id, student_id, attendance_status_id)');
        
        echo "✅ Teacher attendance performance indexes created successfully\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS idx_attendance_sessions_teacher_date');
        DB::statement('DROP INDEX IF EXISTS idx_attendance_sessions_teacher_subject');
        DB::statement('DROP INDEX IF EXISTS idx_teacher_section_subject_active');
        DB::statement('DROP INDEX IF EXISTS idx_student_section_active');
        DB::statement('DROP INDEX IF EXISTS idx_student_details_status');
        DB::statement('DROP INDEX IF EXISTS idx_attendance_records_session_student');
        DB::statement('DROP INDEX IF EXISTS idx_attendance_trends_composite');
        
        echo "✅ Teacher attendance performance indexes dropped\n";
    }
};
