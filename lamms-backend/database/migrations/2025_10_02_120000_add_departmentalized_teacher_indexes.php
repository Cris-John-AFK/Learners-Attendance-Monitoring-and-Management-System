<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add performance indexes for departmentalized teacher queries
     */
    public function up(): void
    {
        // Index for finding teacher assignments by teacher
        if (!$this->indexExists('teacher_section_subject', 'idx_tss_teacher_active')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index(['teacher_id', 'is_active'], 'idx_tss_teacher_active');
            });
        }
        
        // Index for finding assignments by section and subject
        if (!$this->indexExists('teacher_section_subject', 'idx_tss_section_subject')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index(['section_id', 'subject_id'], 'idx_tss_section_subject');
            });
        }
        
        // Index for finding primary (homeroom) teachers
        if (!$this->indexExists('teacher_section_subject', 'idx_tss_is_primary')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index(['is_primary', 'section_id'], 'idx_tss_is_primary');
            });
        }
        
        // Index for role-based queries (homeroom vs subject_teacher)
        if (!$this->indexExists('teacher_section_subject', 'idx_tss_role')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index('role', 'idx_tss_role');
            });
        }
        
        // Composite index for complete teacher assignment lookups
        if (!$this->indexExists('teacher_section_subject', 'idx_tss_teacher_section_subject')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                $table->index(['teacher_id', 'section_id', 'subject_id', 'is_active'], 'idx_tss_teacher_section_subject');
            });
        }
        
        // Index for sections with homeroom teacher
        if (!$this->indexExists('sections', 'idx_sections_homeroom_teacher')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->index('homeroom_teacher_id', 'idx_sections_homeroom_teacher');
            });
        }
        
        echo "✅ Departmentalized teacher performance indexes created\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            $table->dropIndex('idx_tss_teacher_active');
            $table->dropIndex('idx_tss_section_subject');
            $table->dropIndex('idx_tss_is_primary');
            $table->dropIndex('idx_tss_role');
            $table->dropIndex('idx_tss_teacher_section_subject');
        });
        
        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex('idx_sections_homeroom_teacher');
        });
        
        echo "✅ Departmentalized teacher performance indexes dropped\n";
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("
            SELECT indexname 
            FROM pg_indexes 
            WHERE tablename = ? AND indexname = ?
        ", [$table, $index]);
        
        return count($indexes) > 0;
    }
};
