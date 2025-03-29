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
            // Check if is_primary column exists before adding it
            if (!Schema::hasColumn('teacher_section_subject', 'is_primary')) {
                // Add is_primary column after role with default value false
                $table->boolean('is_primary')->default(false)->after('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_section_subject', function (Blueprint $table) {
            // Check if is_primary column exists before dropping it
            if (Schema::hasColumn('teacher_section_subject', 'is_primary')) {
                // Drop the is_primary column if migration is rolled back
                $table->dropColumn('is_primary');
            }
        });
    }
};
