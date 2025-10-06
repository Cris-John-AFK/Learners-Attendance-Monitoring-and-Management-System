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
        Schema::create('sf2_attendance_edits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('section_id');
            $table->date('date');
            $table->string('month', 7); // Format: YYYY-MM
            $table->string('status', 20); // present, absent, late, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['section_id', 'month']);
            $table->index(['student_id', 'date']);
            
            // Unique constraint to prevent duplicate edits
            $table->unique(['student_id', 'date', 'section_id', 'month'], 'sf2_edits_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sf2_attendance_edits');
    }
};
