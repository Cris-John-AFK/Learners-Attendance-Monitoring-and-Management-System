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
            $table->text('description')->nullable();
            $table->integer('credits')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Add a unique constraint on name to prevent duplicates
            $table->unique('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('subjects');
    }
};
