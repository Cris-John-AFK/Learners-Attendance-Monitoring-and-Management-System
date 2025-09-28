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
        // Critical indexes for dashboard performance
        
        // 1. Teacher-Section-Subject indexes
        if (Schema::hasTable('teacher_section_subject')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index(['teacher_id', 'is_active'], 'idx_tss_teacher_active');
                $table->index(['section_id', 'is_active'], 'idx_tss_section_active');
            });
        }

        // 2. Student-Section indexes
        if (Schema::hasTable('student_section')) {
            Schema::table('student_section', function (Blueprint $table) {
                $table->index(['section_id', 'is_active'], 'idx_student_section_active');
                $table->index(['student_id', 'is_active'], 'idx_student_section_student');
            });
        }

        // 3. Attendances table indexes
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index(['teacher_id', 'date'], 'idx_attendances_teacher_date');
                $table->index(['student_id', 'date'], 'idx_attendances_student_date');
                $table->index(['section_id', 'date'], 'idx_attendances_section_date');
            });
        }

        // 4. Notifications table indexes (already has some, add missing ones)
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Check if index doesn't exist before creating
                if (!$this->indexExists('notifications', 'idx_notifications_type_user')) {
                    $table->index(['type', 'user_id'], 'idx_notifications_type_user');
                }
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists($table, $indexName)
    {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes($table);
        
        return array_key_exists($indexName, $indexes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        if (Schema::hasTable('teacher_section_subject')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->dropIndex('idx_tss_teacher_active');
                $table->dropIndex('idx_tss_section_active');
            });
        }

        if (Schema::hasTable('student_section')) {
            Schema::table('student_section', function (Blueprint $table) {
                $table->dropIndex('idx_student_section_active');
                $table->dropIndex('idx_student_section_student');
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropIndex('idx_attendances_teacher_date');
                $table->dropIndex('idx_attendances_student_date');
                $table->dropIndex('idx_attendances_section_date');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('idx_notifications_type_user');
            });
        }
    }
};
