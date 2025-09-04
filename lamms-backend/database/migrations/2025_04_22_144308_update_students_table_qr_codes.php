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
            // Add QR code column if it doesn't exist
            if (!Schema::hasColumn('student_details', 'qr_code')) {
                $table->string('qr_code')->unique()->nullable();
            }

            // Add status column if it doesn't exist
            if (!Schema::hasColumn('student_details', 'status')) {
                $table->enum('status', ['active', 'inactive', 'transferred', 'graduated'])->default('active');
            }

            // Ensure student_id is unique
            if (!Schema::hasColumn('student_details', 'student_id')) {
                $table->string('student_id')->unique();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropColumn(['qr_code', 'status']);
        });
    }
};
