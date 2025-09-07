<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix the unique constraint to only apply to active sessions
     * This allows multiple completed sessions but only one active session per combination
     */
    public function up(): void
    {
        // Drop the existing unique constraint that includes status
        DB::statement('ALTER TABLE attendance_sessions DROP CONSTRAINT IF EXISTS unique_active_session');
        
        // Create a partial unique index that only applies to active sessions
        DB::statement('
            CREATE UNIQUE INDEX unique_active_session_only 
            ON attendance_sessions (teacher_id, section_id, subject_id, session_date) 
            WHERE status = \'active\'
        ');
        
        // Also create a regular index for performance on completed sessions
        DB::statement('
            CREATE INDEX idx_completed_sessions 
            ON attendance_sessions (teacher_id, section_id, subject_id, session_date, status) 
            WHERE status = \'completed\'
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new indexes
        DB::statement('DROP INDEX IF EXISTS unique_active_session_only');
        DB::statement('DROP INDEX IF EXISTS idx_completed_sessions');
        
        // Recreate the original constraint (this might fail if there are duplicates)
        try {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->unique(['teacher_id', 'section_id', 'subject_id', 'session_date', 'status'], 'unique_active_session');
            });
        } catch (\Exception $e) {
            // If we can't recreate the constraint due to existing data, log it
            \Log::warning('Could not recreate original unique constraint: ' . $e->getMessage());
        }
    }
};
