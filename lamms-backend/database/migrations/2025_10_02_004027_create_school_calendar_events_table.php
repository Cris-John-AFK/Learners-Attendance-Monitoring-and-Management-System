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
        Schema::create('school_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // e.g., "Christmas Holiday"
            $table->text('description')->nullable();
            $table->date('start_date'); // Event start date
            $table->date('end_date'); // Event end date (for multi-day events)
            $table->enum('event_type', [
                'holiday',           // No classes - national/school holiday
                'half_day',          // Half day schedule
                'early_dismissal',   // Classes end early
                'no_classes',        // No classes for specific reason
                'school_event',      // School event day (may have modified schedule)
                'teacher_training',  // Teachers only, no students
                'exam_day'          // Special exam schedule
            ]);
            $table->boolean('affects_attendance')->default(true); // Does this prevent auto-absence?
            $table->time('modified_start_time')->nullable(); // For half-days
            $table->time('modified_end_time')->nullable();
            $table->json('affected_sections')->nullable(); // Specific sections affected (null = all)
            $table->json('affected_grade_levels')->nullable(); // Specific grades (null = all)
            $table->boolean('is_recurring')->default(false); // For weekly events
            $table->string('recurrence_pattern')->nullable(); // e.g., "every_monday"
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable(); // Admin/teacher who created
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['start_date', 'end_date']);
            $table->index('event_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_calendar_events');
    }
};
