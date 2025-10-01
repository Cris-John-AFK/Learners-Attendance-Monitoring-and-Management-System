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
        // Check and add indexes for attendance_records
        if (!$this->indexExists('attendance_records', 'idx_ar_student_id')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->index('student_id', 'idx_ar_student_id');
            });
        }
        
        if (!$this->indexExists('attendance_records', 'idx_ar_session_id')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->index('attendance_session_id', 'idx_ar_session_id');
            });
        }
        
        if (!$this->indexExists('attendance_records', 'idx_ar_status_id')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->index('attendance_status_id', 'idx_ar_status_id');
            });
        }
        
        if (!$this->indexExists('attendance_records', 'idx_ar_student_session')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->index(['student_id', 'attendance_session_id'], 'idx_ar_student_session');
            });
        }

        // Check and add indexes for attendance_sessions
        if (!$this->indexExists('attendance_sessions', 'idx_as_session_date')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index('session_date', 'idx_as_session_date');
            });
        }
        
        if (!$this->indexExists('attendance_sessions', 'idx_as_subject_id')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index('subject_id', 'idx_as_subject_id');
            });
        }
        
        if (!$this->indexExists('attendance_sessions', 'idx_as_date_subject')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index(['session_date', 'subject_id'], 'idx_as_date_subject');
            });
        }
        
        if (!$this->indexExists('attendance_sessions', 'idx_as_teacher_id')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index('teacher_id', 'idx_as_teacher_id');
            });
        }
        
        if (!$this->indexExists('attendance_sessions', 'idx_as_section_id')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index('section_id', 'idx_as_section_id');
            });
        }
        
        if (!$this->indexExists('attendance_sessions', 'idx_as_teacher_subject_date')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index(['teacher_id', 'subject_id', 'session_date'], 'idx_as_teacher_subject_date');
            });
        }
    }
    
    /**
     * Check if an index exists
     */
    private function indexExists($table, $indexName): bool
    {
        $connection = Schema::getConnection();
        $schemaName = $connection->getConfig('schema') ?: 'public';
        
        $query = "SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?";
        $result = $connection->selectOne($query, [$schemaName, $table, $indexName]);
        
        return $result !== null;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_ar_student_id');
            $table->dropIndex('idx_ar_session_id');
            $table->dropIndex('idx_ar_status_id');
            $table->dropIndex('idx_ar_student_session');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_as_session_date');
            $table->dropIndex('idx_as_subject_id');
            $table->dropIndex('idx_as_date_subject');
            $table->dropIndex('idx_as_teacher_id');
            $table->dropIndex('idx_as_section_id');
            $table->dropIndex('idx_as_teacher_subject_date');
        });
    }
};
