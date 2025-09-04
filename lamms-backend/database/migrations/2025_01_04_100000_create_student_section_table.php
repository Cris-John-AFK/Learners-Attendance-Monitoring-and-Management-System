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
        Schema::create('student_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_details')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->string('school_year')->default('2025-2026');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ensure a student can only be in one active section per school year
            $table->unique(['student_id', 'school_year', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_section');
    }
};
