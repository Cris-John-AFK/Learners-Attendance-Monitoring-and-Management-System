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
        // Add performance indexes for guardhouse tables
        
        // Guardhouse attendance table indexes
        DB::statement('CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_date_type ON guardhouse_attendance (date, record_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_timestamp ON guardhouse_attendance (timestamp DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_guardhouse_attendance_student_date ON guardhouse_attendance (student_id, date)');
        
        // Guardhouse archived records indexes for search and filtering
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_session ON guardhouse_archived_records (session_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_timestamp ON guardhouse_archived_records (timestamp DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_student_name ON guardhouse_archived_records (student_name)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_grade_level ON guardhouse_archived_records (grade_level)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_section ON guardhouse_archived_records (section)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_record_type ON guardhouse_archived_records (record_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_session_date ON guardhouse_archived_records (session_date)');
        
        // Composite indexes for common filter combinations
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_session_type ON guardhouse_archived_records (session_id, record_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_session_grade ON guardhouse_archived_records (session_id, grade_level)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_session_section ON guardhouse_archived_records (session_id, section)');
        
        // Full-text search index for student names (PostgreSQL specific)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archived_records_student_name_gin ON guardhouse_archived_records USING gin(to_tsvector(\'english\', student_name))');
        
        // Archive sessions table indexes
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archive_sessions_date ON guardhouse_archive_sessions (session_date DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_archive_sessions_archived_at ON guardhouse_archive_sessions (archived_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the indexes
        DB::statement('DROP INDEX IF EXISTS idx_guardhouse_attendance_date_type');
        DB::statement('DROP INDEX IF EXISTS idx_guardhouse_attendance_timestamp');
        DB::statement('DROP INDEX IF EXISTS idx_guardhouse_attendance_student_date');
        
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_session');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_timestamp');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_student_name');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_grade_level');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_section');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_record_type');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_session_date');
        
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_session_type');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_session_grade');
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_session_section');
        
        DB::statement('DROP INDEX IF EXISTS idx_archived_records_student_name_gin');
        
        DB::statement('DROP INDEX IF EXISTS idx_archive_sessions_date');
        DB::statement('DROP INDEX IF EXISTS idx_archive_sessions_archived_at');
    }
};
