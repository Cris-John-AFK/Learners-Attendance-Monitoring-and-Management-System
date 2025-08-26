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
        Schema::table('students', function (Blueprint $table) {
            // Add QR code column if it doesn't exist
            if (!Schema::hasColumn('students', 'qr_code')) {
                $table->string('qr_code')->unique()->nullable();
            }
            
            // Add status column if it doesn't exist
            if (!Schema::hasColumn('students', 'status')) {
                $table->enum('status', ['active', 'inactive', 'transferred', 'graduated'])->default('active');
            }
            
            // Ensure student_id is unique
            if (!Schema::hasColumn('students', 'student_id')) {
                $table->string('student_id')->unique();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['qr_code', 'status']);
        });
    }
};
