<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('gradeLevel');
            $table->string('section');
            $table->string('studentId')->unique();
            $table->string('gender');
            $table->string('contactInfo')->nullable();
            $table->string('parentName')->nullable();
            $table->string('parentContact')->nullable();
            $table->string('profilePhoto')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('students');
    }
};
