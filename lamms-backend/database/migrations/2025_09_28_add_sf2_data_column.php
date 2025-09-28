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
        Schema::table('submitted_sf2_reports', function (Blueprint $table) {
            $table->longText('sf2_data')->nullable()->after('admin_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submitted_sf2_reports', function (Blueprint $table) {
            $table->dropColumn('sf2_data');
        });
    }
};
