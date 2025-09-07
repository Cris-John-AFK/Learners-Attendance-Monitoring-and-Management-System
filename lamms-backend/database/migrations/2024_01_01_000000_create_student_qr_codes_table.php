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
        // Skip this migration - QR code functionality is handled in students table
        // This migration references students table which may not exist yet
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        return;
    }
};
