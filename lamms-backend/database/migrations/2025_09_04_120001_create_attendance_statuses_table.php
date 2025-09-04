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
        Schema::create('attendance_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // e.g., 'P', 'A', 'L', 'E', 'NT', 'AA', 'DK', 'AG', 'GG'
            $table->string('name', 100); // e.g., 'Present', 'Absent', 'Late', 'Excused'
            $table->string('description')->nullable(); // Full description
            $table->string('color', 7)->default('#000000'); // Hex color for UI
            $table->string('background_color', 7)->default('#FFFFFF'); // Background color for UI
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // For ordering in dropdowns
            $table->timestamps();
        });

        // Insert default attendance statuses
        DB::table('attendance_statuses')->insert([
            [
                'code' => 'P',
                'name' => 'Present',
                'description' => 'Student is present in class',
                'color' => '#FFFFFF',
                'background_color' => '#10B981', // Green
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'A',
                'name' => 'Absent',
                'description' => 'Student is absent from class',
                'color' => '#FFFFFF',
                'background_color' => '#EF4444', // Red
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'L',
                'name' => 'Late',
                'description' => 'Student arrived late to class',
                'color' => '#FFFFFF',
                'background_color' => '#F59E0B', // Yellow
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'E',
                'name' => 'Excused',
                'description' => 'Student has an excused absence',
                'color' => '#FFFFFF',
                'background_color' => '#6366F1', // Indigo
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ],

        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_statuses');
    }
};