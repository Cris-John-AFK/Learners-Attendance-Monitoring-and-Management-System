<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_subject', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id');
            $table->unsignedBigInteger('grade_id');
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('grade_id')->references('id')->on('grades')->onDelete('cascade');

            // Prevent duplicate assignments
            $table->unique(['subject_id', 'grade_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_subject');
    }
};
