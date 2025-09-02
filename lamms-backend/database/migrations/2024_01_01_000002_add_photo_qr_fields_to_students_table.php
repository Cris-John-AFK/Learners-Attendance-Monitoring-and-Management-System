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
        // Check if students table exists before trying to modify it
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (!Schema::hasColumn('students', 'photo')) {
                    $table->string('photo')->nullable();
                }
                if (!Schema::hasColumn('students', 'qr_code_path')) {
                    $table->string('qr_code_path')->nullable();
                }
                if (!Schema::hasColumn('students', 'address')) {
                    $table->text('address')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                $columns = ['photo', 'qr_code_path', 'address'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('students', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
