<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the table and columns exist before attempting to drop them
        if (Schema::hasTable('teacher_section_subject')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                if (Schema::hasColumn('teacher_section_subject', 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn('teacher_section_subject', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('teacher_section_subject')) {
            Schema::table('teacher_section_subject', function (Blueprint $table) {
                if (!Schema::hasColumn('teacher_section_subject', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('teacher_section_subject', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }
};
