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
        Schema::table('attendances', function (Blueprint $table) {
            // Add section_id to track which section the attendance is for
            $table->unsignedBigInteger('section_id')->nullable()->after('student_id');
            
            // Add foreign key for section
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
            
            // Drop the old unique constraint
            $table->dropUnique(['student_id', 'subject_id', 'date']);
            
            // Add new unique constraint that includes section
            $table->unique(['student_id', 'section_id', 'subject_id', 'date'], 'unique_student_section_subject_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('unique_student_section_subject_date');
            
            // Remove section_id column
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
            
            // Restore the old unique constraint
            $table->unique(['student_id', 'subject_id', 'date']);
        });
    }
};