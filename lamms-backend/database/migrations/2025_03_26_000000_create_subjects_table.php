<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('grade');
            $table->text('description')->nullable();
            $table->integer('credits')->default(3);
            $table->timestamps();

            // Add a unique constraint to ensure no duplicates of name+grade
            $table->unique(['name', 'grade']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subjects');
    }
};
