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
        // Add schedule_id to attendance_sessions table for linking sessions to schedules
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Add foreign key to subject_schedules (nullable for backward compatibility)
            $table->foreignId('schedule_id')->nullable()->constrained('subject_schedules')->onDelete('set null');
            
            // Add session timing fields for schedule validation
            $table->time('scheduled_start_time')->nullable()->comment('Expected start time from schedule');
            $table->time('scheduled_end_time')->nullable()->comment('Expected end time from schedule');
            $table->timestamp('actual_start_time')->nullable()->comment('When teacher actually started session');
            $table->timestamp('actual_end_time')->nullable()->comment('When session was actually ended');
            
            // Add auto-marking fields
            $table->boolean('auto_absence_marked')->default(false)->comment('Whether auto-absence marking was done');
            $table->timestamp('auto_absence_marked_at')->nullable()->comment('When auto-absence marking occurred');
            
            // Add session validation fields
            $table->boolean('started_on_schedule')->nullable()->comment('Whether session was started within scheduled time');
            $table->integer('minutes_early')->nullable()->comment('Minutes started before scheduled time (positive = early)');
            $table->integer('minutes_late')->nullable()->comment('Minutes started after scheduled time (positive = late)');
            
            // Add notification tracking
            $table->json('notifications_sent')->nullable()->comment('Track which notifications were sent');
        });

        // Create indexes for performance optimization
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Index for finding sessions by schedule
            $table->index(['schedule_id', 'session_date'], 'idx_sessions_schedule_date');
            
            // Index for auto-absence processing (find sessions that need auto-marking)
            $table->index(['session_date', 'auto_absence_marked', 'scheduled_end_time'], 'idx_sessions_auto_absence');
            
            // Index for notification processing (find upcoming sessions)
            $table->index(['session_date', 'scheduled_start_time', 'teacher_id'], 'idx_sessions_notifications');
            
            // Index for session validation queries
            $table->index(['teacher_id', 'session_date', 'scheduled_start_time'], 'idx_sessions_validation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_sessions_schedule_date');
            $table->dropIndex('idx_sessions_auto_absence');
            $table->dropIndex('idx_sessions_notifications');
            $table->dropIndex('idx_sessions_validation');
            
            // Drop foreign key constraint
            $table->dropForeign(['schedule_id']);
            
            // Drop columns
            $table->dropColumn([
                'schedule_id',
                'scheduled_start_time',
                'scheduled_end_time',
                'actual_start_time',
                'actual_end_time',
                'auto_absence_marked',
                'auto_absence_marked_at',
                'started_on_schedule',
                'minutes_early',
                'minutes_late',
                'notifications_sent'
            ]);
        });
    }
};
