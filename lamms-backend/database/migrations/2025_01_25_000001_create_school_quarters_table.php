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
        // Create school_quarters table
        Schema::create('school_quarters', function (Blueprint $table) {
            $table->id();
            $table->string('school_year'); // e.g., "2024-2025"
            $table->string('quarter'); // e.g., "1st", "2nd", "3rd", "4th"
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->timestamps();

            // Unique constraint: one quarter per school year
            $table->unique(['school_year', 'quarter']);
        });

        // Create quarter_teacher_access pivot table
        Schema::create('quarter_teacher_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quarter_id')->constrained('school_quarters')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint: one teacher can only have one access record per quarter
            $table->unique(['quarter_id', 'teacher_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quarter_teacher_access');
        Schema::dropIfExists('school_quarters');
    }
};
