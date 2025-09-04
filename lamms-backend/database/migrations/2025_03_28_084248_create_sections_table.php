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
        // Skip this migration - sections table already created by 2024_12_26_180000_create_sections_table.php
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip - handled by earlier migration
        return;
    }
};
