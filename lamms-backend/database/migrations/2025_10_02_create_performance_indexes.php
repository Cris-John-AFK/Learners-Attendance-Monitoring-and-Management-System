<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Log start of indexing
        echo "🚀 Creating performance indexes for attendance system...\n";
        
        // 1. Teacher Section Subject indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tss_teacher_id ON teacher_section_subject(teacher_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tss_section_id ON teacher_section_subject(section_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tss_subject_id ON teacher_section_subject(subject_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tss_teacher_section_subject ON teacher_section_subject(teacher_id, section_id, subject_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tss_active ON teacher_section_subject(is_active) WHERE is_active = true');
            echo "✅ Teacher section subject indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some TSS indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 2. Attendance Sessions indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_teacher_id ON attendance_sessions(teacher_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_section_id ON attendance_sessions(section_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_subject_id ON attendance_sessions(subject_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_date ON attendance_sessions(session_date)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_composite ON attendance_sessions(teacher_id, section_id, subject_id, session_date)');
            echo "✅ Attendance sessions indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some attendance session indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 3. Attendance Records indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_student_id ON attendance_records(student_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_session_id ON attendance_records(attendance_session_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_status ON attendance_records(attendance_status_code)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_composite ON attendance_records(student_id, attendance_session_id, attendance_status_code)');
            echo "✅ Attendance records indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some attendance record indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 4. Student Section indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_section_student_id ON student_section(student_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_section_section_id ON student_section(section_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_section_active ON student_section(is_active) WHERE is_active = true');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_section_composite ON student_section(section_id, student_id) WHERE is_active = true');
            echo "✅ Student section indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some student section indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 5. Student Details indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_details_student_id ON student_details(student_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_details_lrn ON student_details(lrn)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_details_status ON student_details(status)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_student_details_grade ON student_details(gradeLevel)');
            echo "✅ Student details indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some student detail indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 6. Sections indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_sections_curriculum_grade ON sections(curriculum_grade_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_sections_homeroom_teacher ON sections(homeroom_teacher_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_sections_active ON sections(is_active) WHERE is_active = true');
            echo "✅ Sections indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some section indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 7. Subject Schedules indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_subject_schedules_section_id ON subject_schedules(section_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_subject_schedules_subject_id ON subject_schedules(subject_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_subject_schedules_teacher_id ON subject_schedules(teacher_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_subject_schedules_day ON subject_schedules(day_of_week)');
            echo "✅ Subject schedules indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some subject schedule indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 8. Attendance Statuses indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_statuses_code ON attendance_statuses(code)');
            echo "✅ Attendance statuses indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some attendance status indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 9. Teachers indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_teachers_user_id ON teachers(user_id)');
            echo "✅ Teachers indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some teacher indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        // 10. Users indexes (for teacher authentication)
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
            echo "✅ Users indexes created\n";
        } catch (\Exception $e) {
            echo "⚠️ Some user indexes may already exist: " . $e->getMessage() . "\n";
        }
        
        echo "🎉 All performance indexes have been created successfully!\n";
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop all indexes
        $indexes = [
            // TSS indexes
            'idx_tss_teacher_id',
            'idx_tss_section_id', 
            'idx_tss_subject_id',
            'idx_tss_teacher_section_subject',
            'idx_tss_active',
            
            // Attendance sessions
            'idx_attendance_sessions_teacher_id',
            'idx_attendance_sessions_section_id',
            'idx_attendance_sessions_subject_id',
            'idx_attendance_sessions_date',
            'idx_attendance_sessions_composite',
            
            // Attendance records
            'idx_attendance_records_student_id',
            'idx_attendance_records_session_id',
            'idx_attendance_records_status',
            'idx_attendance_records_composite',
            
            // Student section
            'idx_student_section_student_id',
            'idx_student_section_section_id',
            'idx_student_section_active',
            'idx_student_section_composite',
            
            // Student details
            'idx_student_details_student_id',
            'idx_student_details_lrn',
            'idx_student_details_status',
            'idx_student_details_grade',
            
            // Sections
            'idx_sections_curriculum_grade',
            'idx_sections_homeroom_teacher',
            'idx_sections_active',
            
            // Subject schedules
            'idx_subject_schedules_section_id',
            'idx_subject_schedules_subject_id',
            'idx_subject_schedules_teacher_id',
            'idx_subject_schedules_day',
            
            // Attendance statuses
            'idx_attendance_statuses_code',
            
            // Teachers
            'idx_teachers_user_id',
            
            // Users
            'idx_users_username',
            'idx_users_role'
        ];
        
        foreach ($indexes as $index) {
            try {
                DB::statement("DROP INDEX IF EXISTS $index");
            } catch (\Exception $e) {
                // Index might not exist, continue
            }
        }
        
        echo "All performance indexes have been dropped.\n";
    }
}
