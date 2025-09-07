<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        // Skip this migration - replaced by 2025_04_22_144310_create_student_section_table.php
        // This migration was running before student_details table was created
        return;
    }
    public function down(): void
    {
        // Skip this migration - replaced by 2025_04_22_144310_create_student_section_table.php
        return;
    }
};
