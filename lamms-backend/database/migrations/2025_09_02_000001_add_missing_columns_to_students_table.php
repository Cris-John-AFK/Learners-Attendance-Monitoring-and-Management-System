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
        Schema::table('student_details', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('student_details', 'address')) {
                $table->text('address')->nullable();
            }
            if (!Schema::hasColumn('student_details', 'qr_code_path')) {
                $table->string('qr_code_path')->nullable();
            }
            if (!Schema::hasColumn('student_details', 'photo')) {
                $table->string('photo')->nullable();
            }
            if (!Schema::hasColumn('student_details', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropColumn(['address', 'qr_code_path', 'photo', 'is_active']);
        });
    }
};
