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
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            // Check if role column exists before adding it
            if (!Schema::hasColumn('teacher_section_subject', 'role')) {
                // Add role column after is_active with default value 'subject'
                $table->string('role')->default('subject')->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            // Check if role column exists before dropping it
            if (Schema::hasColumn('teacher_section_subject', 'role')) {
                // Drop the role column if migration is rolled back
                $table->dropColumn('role');
            }
        });
    }
};
