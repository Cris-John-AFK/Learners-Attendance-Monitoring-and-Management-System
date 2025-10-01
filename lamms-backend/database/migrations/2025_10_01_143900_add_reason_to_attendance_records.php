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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('reason_id')->nullable()->after('status')->constrained('attendance_reasons')->onDelete('set null');
            $table->text('reason_notes')->nullable()->after('reason_id'); // Additional notes for the reason
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['reason_id']);
            $table->dropColumn(['reason_id', 'reason_notes']);
        });
    }
};
