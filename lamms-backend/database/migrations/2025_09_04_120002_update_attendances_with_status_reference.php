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
        Schema::table('attendances', function (Blueprint $table) {
            // Add attendance_status_id to reference the attendance_statuses table
            $table->unsignedBigInteger('attendance_status_id')->nullable()->after('status');
            
            // Add foreign key for attendance status
            $table->foreign('attendance_status_id')->references('id')->on('attendance_statuses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Remove foreign key and column
            $table->dropForeign(['attendance_status_id']);
            $table->dropColumn('attendance_status_id');
        });
    }
};