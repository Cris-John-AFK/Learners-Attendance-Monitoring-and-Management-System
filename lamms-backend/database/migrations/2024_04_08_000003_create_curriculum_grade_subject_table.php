<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('curriculum_grade_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('units')->default(1);
            $table->unsignedInteger('hours_per_week')->default(1);
            $table->unsignedInteger('sequence_number')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            // Ensure unique combination of curriculum, grade, and subject
            $table->unique(['curriculum_id', 'grade_id', 'subject_id'], 'cgs_unique');

            // Ensure unique sequence number within a curriculum-grade combination
            $table->unique(['curriculum_id', 'grade_id', 'sequence_number'], 'cgs_sequence');

            // Add foreign key constraint to ensure curriculum_grade relationship exists
            $table->foreign(['curriculum_id', 'grade_id'], 'cgs_curriculum_grade_foreign')
                  ->references(['curriculum_id', 'grade_id'])
                  ->on('curriculum_grade')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('curriculum_grade_subject');
    }
};
