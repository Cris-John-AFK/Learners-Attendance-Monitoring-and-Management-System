<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Sync homeroom teachers from teacher_section_subject to sections table
     */
    public function up(): void
    {
        // Update sections table with homeroom teacher from teacher_section_subject
        DB::statement("
            UPDATE sections 
            SET homeroom_teacher_id = (
                SELECT teacher_id 
                FROM teacher_section_subject 
                WHERE section_id = sections.id 
                AND role = 'homeroom' 
                AND is_active = true 
                LIMIT 1
            )
            WHERE id IN (
                SELECT DISTINCT section_id 
                FROM teacher_section_subject 
                WHERE role = 'homeroom' 
                AND is_active = true
            )
        ");
        
        echo "✅ Synced homeroom teachers from teacher_section_subject to sections table\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - this is a data sync
    }
};
