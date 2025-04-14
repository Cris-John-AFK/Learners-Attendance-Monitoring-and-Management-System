<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('curriculum_grade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure unique combination of curriculum and grade
            $table->unique(['curriculum_id', 'grade_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('curriculum_grade');
    }
};
