<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateTeacherSectionSubjectNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            // Make subject_id nullable for homeroom teachers
            DB::statement('ALTER TABLE teacher_section_subject ALTER COLUMN subject_id DROP NOT NULL');

            // Add a check constraint to ensure subject_id is NOT NULL unless role is 'homeroom'
            DB::statement("
                ALTER TABLE teacher_section_subject
                ADD CONSTRAINT check_subject_id_or_homeroom
                CHECK (subject_id IS NOT NULL OR role = 'homeroom')
            ");
        } catch (\Exception $e) {
            // Log the error and continue - this might fail if the constraint already exists
            // or if the database doesn't support this kind of constraint
            \Illuminate\Support\Facades\Log::error('Migration error: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        try {
            // Remove the check constraint if it exists
            DB::statement('ALTER TABLE teacher_section_subject DROP CONSTRAINT IF EXISTS check_subject_id_or_homeroom');

            // Make subject_id NOT NULL again
            // First, clean up any nulls by setting them to a default subject
            $defaultSubjectId = DB::table('subjects')->first()->id ?? 1;
            DB::table('teacher_section_subject')
                ->whereNull('subject_id')
                ->update(['subject_id' => $defaultSubjectId]);

            // Then add the NOT NULL constraint back
            DB::statement('ALTER TABLE teacher_section_subject ALTER COLUMN subject_id SET NOT NULL');
        } catch (\Exception $e) {
            // Log the error and continue
            \Illuminate\Support\Facades\Log::error('Migration rollback error: ' . $e->getMessage());
        }
    }
}
