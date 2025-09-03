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
        // Check if student_details table exists before trying to modify it
        if (Schema::hasTable('student_details')) {
            Schema::table('student_details', function (Blueprint $table) {
                if (!Schema::hasColumn('student_details', 'photo')) {
                    $table->string('photo')->nullable();
                }
                if (!Schema::hasColumn('student_details', 'qr_code_path')) {
                    $table->string('qr_code_path')->nullable();
                }
                if (!Schema::hasColumn('student_details', 'address')) {
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
        if (Schema::hasTable('student_details')) {
            Schema::table('student_details', function (Blueprint $table) {
                $columns = ['photo', 'qr_code_path', 'address'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('student_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
