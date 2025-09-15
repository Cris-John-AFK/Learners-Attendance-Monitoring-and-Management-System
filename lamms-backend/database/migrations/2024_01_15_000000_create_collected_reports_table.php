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
        Schema::create('collected_reports', function (Blueprint $table) {
            $table->id();
            $table->string('grade_level');
            $table->string('section');
            $table->string('school_id');
            $table->string('school_year');
            $table->string('month');
            $table->integer('total_students');
            $table->integer('present_today');
            $table->integer('absent_today');
            $table->decimal('attendance_rate', 5, 2);
            $table->string('teacher_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collected_reports');
    }
};
