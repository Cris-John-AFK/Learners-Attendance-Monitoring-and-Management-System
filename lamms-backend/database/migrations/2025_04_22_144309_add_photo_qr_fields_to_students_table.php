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
<<<<<<< HEAD:lamms-backend/database/migrations/2024_01_01_000002_add_photo_qr_fields_to_students_table.php
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
=======
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('profilePhoto');
            $table->string('qr_code_path')->nullable()->after('photo');
            $table->text('address')->nullable()->after('qr_code_path');
        });
>>>>>>> 9cb1b226dc58a5dce7eb407574f5d14769c1f993:lamms-backend/database/migrations/2025_04_22_144309_add_photo_qr_fields_to_students_table.php
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
