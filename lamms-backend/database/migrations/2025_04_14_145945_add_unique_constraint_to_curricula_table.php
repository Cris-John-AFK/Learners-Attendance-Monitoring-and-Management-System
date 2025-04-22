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
        Schema::table('curricula', function (Blueprint $table) {
            // Add a unique constraint to prevent duplicates with the same name, start_year, and end_year
            $table->unique(['name', 'start_year', 'end_year'], 'curricula_unique_year_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            // Drop the unique constraint if rolling back
            $table->dropUnique('curricula_unique_year_range');
        });
    }
};
