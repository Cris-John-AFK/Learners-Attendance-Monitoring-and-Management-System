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
        // First, add the new columns as nullable
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('curriculum_grade_id')->nullable()->constrained('curriculum_grade')->onDelete('cascade');
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
        });

        // Update existing sections with corresponding curriculum_grade_id
        DB::statement('
            UPDATE sections s
            SET curriculum_grade_id = cg.id
            FROM curriculum_grade cg, grades g
            WHERE s.grade_id = g.id
            AND cg.grade_id = g.id
        ');

        // Drop the old column and make curriculum_grade_id required
        Schema::table('sections', function (Blueprint $table) {
            // Drop the existing foreign key first
            $table->dropForeign(['grade_id']);
            $table->dropColumn('grade_id');
        });

        // Make curriculum_grade_id non-nullable in a separate step
        Schema::table('sections', function (Blueprint $table) {
            DB::statement('ALTER TABLE sections ALTER COLUMN curriculum_grade_id SET NOT NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            // Add back the grade_id column as nullable first
            $table->foreignId('grade_id')->nullable();
        });

        // Restore the data
        DB::statement('
            UPDATE sections s
            SET grade_id = cg.grade_id
            FROM curriculum_grade cg
            WHERE s.curriculum_grade_id = cg.id
        ');

        // Make grade_id non-nullable and add foreign key
        Schema::table('sections', function (Blueprint $table) {
            DB::statement('ALTER TABLE sections ALTER COLUMN grade_id SET NOT NULL');
            $table->foreign('grade_id')->references('id')->on('grades')->onDelete('cascade');
        });

        // Remove the new columns
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['curriculum_grade_id']);
            $table->dropForeign(['homeroom_teacher_id']);
            $table->dropColumn(['curriculum_grade_id', 'homeroom_teacher_id']);
        });
    }
};
