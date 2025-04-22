<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Check if the status column doesn't exist before adding it
        if (!Schema::hasColumn('curricula', 'status')) {
            Schema::table('curricula', function (Blueprint $table) {
                $table->string('status')->default('Draft');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
