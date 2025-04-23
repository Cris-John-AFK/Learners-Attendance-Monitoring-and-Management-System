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
<<<<<<< HEAD
        // Check if the status column doesn't exist before adding it
        if (!Schema::hasColumn('curricula', 'status')) {
            Schema::table('curricula', function (Blueprint $table) {
                $table->string('status')->default('Draft');
            });
        }
=======
        Schema::table('curricula', function (Blueprint $table) {
            if (!Schema::hasColumn('curricula', 'status')) {
                $table->string('status')->default('Draft')->after('is_active');
            }
        });
>>>>>>> e5f68e5f42f7d1ef8c2cb0023fde68fde0a8c8a7
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            if (Schema::hasColumn('curricula', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
