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
            // Add a unique constraint on the combination of start_year and end_year
            $table->unique(['start_year', 'end_year'], 'curricula_year_range_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('curricula_year_range_unique');
        });
    }
};
