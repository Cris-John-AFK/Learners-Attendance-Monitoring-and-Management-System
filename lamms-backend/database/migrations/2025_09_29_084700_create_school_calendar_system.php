<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create DepEd-compliant school calendar system
     */
    public function up(): void
    {
        // School Years table
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "2024-2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->json('quarters')->nullable(); // Store quarter dates
            $table->timestamps();
        });

        // School Holidays table (DepEd holidays)
        Schema::create('school_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Independence Day"
            $table->date('date');
            $table->string('type'); // 'national', 'local', 'school_specific'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // School Days table (actual class days)
        Schema::create('school_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('school_year_id')->constrained('school_years');
            $table->boolean('is_class_day')->default(true);
            $table->string('day_type')->default('regular'); // 'regular', 'half_day', 'special'
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['date', 'school_year_id']);
        });

        // Add school_year_id to attendance_sessions
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('school_year_id')->nullable()->constrained('school_years');
            $table->boolean('is_valid_school_day')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['school_year_id']);
            $table->dropColumn(['school_year_id', 'is_valid_school_day']);
        });
        
        Schema::dropIfExists('school_days');
        Schema::dropIfExists('school_holidays');
        Schema::dropIfExists('school_years');
    }
};
