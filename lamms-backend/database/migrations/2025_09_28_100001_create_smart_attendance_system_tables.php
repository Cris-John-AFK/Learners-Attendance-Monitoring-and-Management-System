<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Smart Attendance System - Database Foundation
     * Phase 1: Create all necessary tables with comprehensive indexing
     */
    public function up()
    {
        echo "🚀 Creating Smart Attendance System Database Foundation...\n";
        echo "=" . str_repeat("=", 60) . "\n\n";

        // 1. STUDENT STATUS CHANGES TABLE
        echo "📊 Creating student_status_changes table...\n";
        Schema::create('student_status_changes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('student_id')->unsigned();
            $table->bigInteger('changed_by_user_id')->unsigned(); // Teacher/Admin who made the change
            $table->string('previous_status')->default('active');
            $table->string('new_status'); // active, dropped_out, transferred_out, suspended, medical_leave
            $table->string('reason_category')->nullable(); // suspended, medical_reasons, moving_away, others
            $table->text('reason_note')->nullable(); // Custom reason when "others" is selected
            $table->date('effective_date'); // When the status change takes effect
            $table->boolean('is_current')->default(true); // Only one current status per student
            $table->timestamp('changed_at');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
            $table->foreign('changed_by_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index('student_id', 'idx_status_changes_student');
            $table->index('new_status', 'idx_status_changes_status');
            $table->index('is_current', 'idx_status_changes_current');
            $table->index('effective_date', 'idx_status_changes_date');
            $table->index(['student_id', 'is_current'], 'idx_status_changes_student_current');
            $table->index(['new_status', 'effective_date'], 'idx_status_changes_status_date');
        });
        echo "✅ student_status_changes table created with 6 performance indexes\n\n";

        // 2. TEACHER NOTES TABLE
        echo "📝 Creating teacher_notes table...\n";
        Schema::create('teacher_notes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('teacher_id')->unsigned();
            $table->bigInteger('student_id')->unsigned()->nullable(); // Null for general notes
            $table->string('title', 100);
            $table->text('content');
            $table->string('color', 20)->default('yellow'); // yellow, blue, pink, green, orange, purple
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('reminder_date')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('set null');
            
            // Indexes for performance
            $table->index('teacher_id', 'idx_teacher_notes_teacher');
            $table->index('student_id', 'idx_teacher_notes_student');
            $table->index('is_archived', 'idx_teacher_notes_archived');
            $table->index('is_pinned', 'idx_teacher_notes_pinned');
            $table->index('color', 'idx_teacher_notes_color');
            $table->index('reminder_date', 'idx_teacher_notes_reminder');
            $table->index(['teacher_id', 'is_archived'], 'idx_teacher_notes_teacher_archived');
            $table->index(['teacher_id', 'student_id'], 'idx_teacher_notes_teacher_student');
        });
        echo "✅ teacher_notes table created with 8 performance indexes\n\n";

        // 3. NOTIFICATIONS TABLE
        echo "🔔 Creating notifications table...\n";
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned(); // Recipient (teacher/admin)
            $table->string('type'); // status_change, attendance_alert, note_reminder, system
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data (student_id, attendance_data, etc.)
            $table->string('priority')->default('normal'); // low, normal, high, critical
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->bigInteger('related_student_id')->unsigned()->nullable();
            $table->bigInteger('created_by_user_id')->unsigned()->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('related_student_id')->references('id')->on('student_details')->onDelete('set null');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index('user_id', 'idx_notifications_user');
            $table->index('type', 'idx_notifications_type');
            $table->index('is_read', 'idx_notifications_read');
            $table->index('priority', 'idx_notifications_priority');
            $table->index('related_student_id', 'idx_notifications_student');
            $table->index('created_at', 'idx_notifications_created');
            $table->index(['user_id', 'is_read'], 'idx_notifications_user_read');
            $table->index(['user_id', 'type'], 'idx_notifications_user_type');
            $table->index(['priority', 'is_read'], 'idx_notifications_priority_read');
        });
        echo "✅ notifications table created with 9 performance indexes\n\n";

        // 4. STUDENT ARCHIVE TABLE
        echo "🗄️ Creating student_archive table...\n";
        Schema::create('student_archive', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('original_student_id')->unsigned();
            $table->json('student_data'); // Full student details snapshot
            $table->json('attendance_summary'); // Summarized attendance data
            $table->json('status_history'); // All status changes
            $table->json('notes_summary')->nullable(); // Teacher notes summary
            $table->string('final_status'); // dropped_out, transferred_out, etc.
            $table->text('archive_reason');
            $table->date('archived_date');
            $table->bigInteger('archived_by_user_id')->unsigned();
            $table->boolean('can_be_restored')->default(true);
            $table->timestamp('auto_archive_date')->nullable(); // When it was auto-archived
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('archived_by_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index('original_student_id', 'idx_archive_original_student');
            $table->index('final_status', 'idx_archive_final_status');
            $table->index('archived_date', 'idx_archive_date');
            $table->index('can_be_restored', 'idx_archive_restorable');
            $table->index('auto_archive_date', 'idx_archive_auto_date');
            $table->index(['final_status', 'archived_date'], 'idx_archive_status_date');
        });
        echo "✅ student_archive table created with 6 performance indexes\n\n";

        // 5. ATTENDANCE ANALYTICS CACHE TABLE (for performance)
        echo "📈 Creating attendance_analytics_cache table...\n";
        Schema::create('attendance_analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('student_id')->unsigned();
            $table->date('analysis_date');
            $table->integer('total_absences_this_year');
            $table->integer('total_tardies_last_30_days');
            $table->decimal('attendance_percentage_last_30_days', 5, 2);
            $table->json('subject_specific_data'); // Per-subject attendance rates
            $table->json('pattern_analysis'); // Detected patterns (Monday absences, etc.)
            $table->string('risk_level'); // low, medium, high, critical
            $table->boolean('exceeds_18_absence_limit')->default(false);
            $table->timestamp('last_updated');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
            
            // Unique constraint - one cache record per student per day
            $table->unique(['student_id', 'analysis_date'], 'unique_student_analysis_date');
            
            // Indexes for performance
            $table->index('student_id', 'idx_analytics_cache_student');
            $table->index('analysis_date', 'idx_analytics_cache_date');
            $table->index('risk_level', 'idx_analytics_cache_risk');
            $table->index('exceeds_18_absence_limit', 'idx_analytics_cache_18_limit');
            $table->index('last_updated', 'idx_analytics_cache_updated');
            $table->index(['risk_level', 'analysis_date'], 'idx_analytics_cache_risk_date');
        });
        echo "✅ attendance_analytics_cache table created with 6 performance indexes\n\n";

        // 6. ADD STATUS COLUMN TO EXISTING STUDENT_DETAILS TABLE
        echo "🔄 Adding status column to student_details table...\n";
        Schema::table('student_details', function (Blueprint $table) {
            $table->string('current_status')->default('active')->after('isActive');
            $table->date('status_changed_date')->nullable()->after('current_status');
            
            // Add indexes for the new columns
            $table->index('current_status', 'idx_student_details_status');
            $table->index('status_changed_date', 'idx_student_details_status_date');
            $table->index(['current_status', 'isActive'], 'idx_student_details_status_active');
        });
        echo "✅ Added status tracking to student_details with 3 performance indexes\n\n";

        echo "🎉 Smart Attendance System Database Foundation Complete!\n";
        echo "=" . str_repeat("=", 55) . "\n";
        echo "📊 Summary:\n";
        echo "   • student_status_changes: 6 indexes\n";
        echo "   • teacher_notes: 8 indexes\n";
        echo "   • notifications: 9 indexes\n";
        echo "   • student_archive: 6 indexes\n";
        echo "   • attendance_analytics_cache: 6 indexes\n";
        echo "   • student_details updates: 3 indexes\n";
        echo "   📈 Total new indexes: 38\n\n";
        
        echo "🚀 Ready for Phase 2: Smart Attendance Analytics Engine!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        echo "🔄 Removing Smart Attendance System tables...\n";
        
        // Remove added columns from student_details
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropIndex('idx_student_details_status');
            $table->dropIndex('idx_student_details_status_date');
            $table->dropIndex('idx_student_details_status_active');
            $table->dropColumn(['current_status', 'status_changed_date']);
        });
        
        // Drop tables in reverse order (respecting foreign keys)
        Schema::dropIfExists('attendance_analytics_cache');
        Schema::dropIfExists('student_archive');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('teacher_notes');
        Schema::dropIfExists('student_status_changes');
        
        echo "✅ Smart Attendance System tables removed successfully!\n";
    }
};
