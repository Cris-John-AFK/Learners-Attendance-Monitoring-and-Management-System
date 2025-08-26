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
        Schema::create('seating_arrangements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('teacher_id');
            $table->json('layout'); // Store seating layout as JSON
            $table->timestamps();
            
            // Unique constraint for section-subject combination
            $table->unique(['section_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seating_arrangements');
    }
};
