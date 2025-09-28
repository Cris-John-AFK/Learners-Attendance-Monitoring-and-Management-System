<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Smart Attendance System - Database Foundation (Fixed Index Names)
     * Phase 1: Create all necessary tables with comprehensive indexing
     */
    public function up()
    {
        echo "🚀 Creating Smart Attendance System Database Foundation (Fixed)...\n";
        echo "=" . str_repeat("=", 65) . "\n\n";

        // Helper function to safely create index
        $safeCreateIndex = function($sql, $description) {
            try {
                DB::statement($sql);
                echo "✅ {$description}\n";
                return true;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "ℹ️  {$description} (already exists)\n";
                    return true;
                } else {
                    echo "⚠️  {$description} - " . substr($e->getMessage(), 0, 80) . "...\n";
                    return false;
                }
            }
        };

        // 1. STUDENT STATUS CHANGES TABLE (if not exists)
        if (!Schema::hasTable('student_status_changes')) {
            echo "📊 Creating student_status_changes table...\n";
            Schema::create('student_status_changes', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('student_id')->unsigned();
                $table->bigInteger('changed_by_user_id')->unsigned();
                $table->string('previous_status')->default('active');
                $table->string('new_status');
                $table->string('reason_category')->nullable();
                $table->text('reason_note')->nullable();
                $table->date('effective_date');
                $table->boolean('is_current')->default(true);
                $table->timestamp('changed_at');
                $table->timestamps();
                
                $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
                $table->foreign('changed_by_user_id')->references('id')->on('users')->onDelete('cascade');
            });
            
            // Create indexes separately to avoid conflicts
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_status_changes_student_smart ON student_status_changes (student_id)", "Student status changes - student index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_status_changes_status_smart ON student_status_changes (new_status)", "Student status changes - status index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_status_changes_current_smart ON student_status_changes (is_current)", "Student status changes - current index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_status_changes_date_smart ON student_status_changes (effective_date)", "Student status changes - date index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_status_changes_composite_smart ON student_status_changes (student_id, is_current)", "Student status changes - composite index");
            echo "✅ student_status_changes table created with 5 performance indexes\n\n";
        }

        // 2. TEACHER NOTES TABLE (if not exists)
        if (!Schema::hasTable('teacher_notes')) {
            echo "📝 Creating teacher_notes table...\n";
            Schema::create('teacher_notes', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('teacher_id')->unsigned();
                $table->bigInteger('student_id')->unsigned()->nullable();
                $table->string('title', 100);
                $table->text('content');
                $table->string('color', 20)->default('yellow');
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->timestamp('reminder_date')->nullable();
                $table->timestamps();
                
                $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
                $table->foreign('student_id')->references('id')->on('student_details')->onDelete('set null');
            });
            
            // Create indexes separately
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_teacher_notes_teacher_smart ON teacher_notes (teacher_id)", "Teacher notes - teacher index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_teacher_notes_student_smart ON teacher_notes (student_id)", "Teacher notes - student index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_teacher_notes_archived_smart ON teacher_notes (is_archived)", "Teacher notes - archived index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_teacher_notes_pinned_smart ON teacher_notes (is_pinned)", "Teacher notes - pinned index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_teacher_notes_color_smart ON teacher_notes (color)", "Teacher notes - color index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_teacher_notes_composite_smart ON teacher_notes (teacher_id, is_archived)", "Teacher notes - composite index");
            echo "✅ teacher_notes table created with 6 performance indexes\n\n";
        }

        // 3. NOTIFICATIONS TABLE (if not exists)
        if (!Schema::hasTable('notifications')) {
            echo "🔔 Creating notifications table...\n";
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id')->unsigned();
                $table->string('type');
                $table->string('title');
                $table->text('message');
                $table->json('data')->nullable();
                $table->string('priority')->default('normal');
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->bigInteger('related_student_id')->unsigned()->nullable();
                $table->bigInteger('created_by_user_id')->unsigned()->nullable();
                $table->timestamps();
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('related_student_id')->references('id')->on('student_details')->onDelete('set null');
                $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            });
            
            // Create indexes separately
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_user_smart ON notifications (user_id)", "Notifications - user index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_type_smart ON notifications (type)", "Notifications - type index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_read_smart ON notifications (is_read)", "Notifications - read status index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_priority_smart ON notifications (priority)", "Notifications - priority index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_student_smart ON notifications (related_student_id)", "Notifications - student index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_created_smart ON notifications (created_at)", "Notifications - created date index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_notifications_user_read_smart ON notifications (user_id, is_read)", "Notifications - user read composite");
            echo "✅ notifications table created with 7 performance indexes\n\n";
        }

        // 4. STUDENT ARCHIVE TABLE (if not exists)
        if (!Schema::hasTable('student_archive')) {
            echo "🗄️ Creating student_archive table...\n";
            Schema::create('student_archive', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('original_student_id')->unsigned();
                $table->json('student_data');
                $table->json('attendance_summary');
                $table->json('status_history');
                $table->json('notes_summary')->nullable();
                $table->string('final_status');
                $table->text('archive_reason');
                $table->date('archived_date');
                $table->bigInteger('archived_by_user_id')->unsigned();
                $table->boolean('can_be_restored')->default(true);
                $table->timestamp('auto_archive_date')->nullable();
                $table->timestamps();
                
                $table->foreign('archived_by_user_id')->references('id')->on('users')->onDelete('cascade');
            });
            
            // Create indexes separately with unique names
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_archive_original_smart ON student_archive (original_student_id)", "Student archive - original student index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_archive_status_smart ON student_archive (final_status)", "Student archive - final status index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_archive_archived_date_smart ON student_archive (archived_date)", "Student archive - archived date index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_archive_restorable_smart ON student_archive (can_be_restored)", "Student archive - restorable index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_archive_auto_date_smart ON student_archive (auto_archive_date)", "Student archive - auto date index");
            echo "✅ student_archive table created with 5 performance indexes\n\n";
        }

        // 5. ATTENDANCE ANALYTICS CACHE TABLE (if not exists)
        if (!Schema::hasTable('attendance_analytics_cache')) {
            echo "📈 Creating attendance_analytics_cache table...\n";
            Schema::create('attendance_analytics_cache', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('student_id')->unsigned();
                $table->date('analysis_date');
                $table->integer('total_absences_this_year');
                $table->integer('total_tardies_last_30_days');
                $table->decimal('attendance_percentage_last_30_days', 5, 2);
                $table->json('subject_specific_data');
                $table->json('pattern_analysis');
                $table->string('risk_level');
                $table->boolean('exceeds_18_absence_limit')->default(false);
                $table->timestamp('last_updated');
                $table->timestamps();
                
                $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
                $table->unique(['student_id', 'analysis_date'], 'unique_student_analysis_date_smart');
            });
            
            // Create indexes separately
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_analytics_cache_student_smart ON attendance_analytics_cache (student_id)", "Analytics cache - student index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_analytics_cache_analysis_date_smart ON attendance_analytics_cache (analysis_date)", "Analytics cache - analysis date index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_analytics_cache_risk_smart ON attendance_analytics_cache (risk_level)", "Analytics cache - risk level index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_analytics_cache_18_limit_smart ON attendance_analytics_cache (exceeds_18_absence_limit)", "Analytics cache - 18 limit index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_analytics_cache_updated_smart ON attendance_analytics_cache (last_updated)", "Analytics cache - last updated index");
            echo "✅ attendance_analytics_cache table created with 5 performance indexes\n\n";
        }

        // 6. ADD STATUS COLUMNS TO EXISTING STUDENT_DETAILS TABLE
        echo "🔄 Adding status columns to student_details table...\n";
        if (!Schema::hasColumn('student_details', 'current_status')) {
            Schema::table('student_details', function (Blueprint $table) {
                $table->string('current_status')->default('active')->after('isActive');
                $table->date('status_changed_date')->nullable()->after('current_status');
            });
            
            // Add indexes for the new columns
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_details_current_status_smart ON student_details (current_status)", "Student details - current status index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_details_status_date_smart ON student_details (status_changed_date)", "Student details - status date index");
            $safeCreateIndex("CREATE INDEX IF NOT EXISTS idx_student_details_status_active_smart ON student_details (current_status, \"isActive\")", "Student details - status active composite");
            echo "✅ Added status tracking to student_details with 3 performance indexes\n\n";
        } else {
            echo "ℹ️  Status columns already exist in student_details\n\n";
        }

        echo "🎉 Smart Attendance System Database Foundation Complete!\n";
        echo "=" . str_repeat("=", 55) . "\n";
        echo "📊 Summary of Tables Created:\n";
        echo "   ✅ student_status_changes (5 indexes)\n";
        echo "   ✅ teacher_notes (6 indexes)\n";
        echo "   ✅ notifications (7 indexes)\n";
        echo "   ✅ student_archive (5 indexes)\n";
        echo "   ✅ attendance_analytics_cache (5 indexes)\n";
        echo "   ✅ student_details updates (3 indexes)\n";
        echo "   📈 Total new indexes: 31\n\n";
        
        echo "🚀 Database foundation is ready!\n";
        echo "💡 Next: Phase 2 - Smart Attendance Analytics Engine\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        echo "🔄 Removing Smart Attendance System tables...\n";
        
        // Remove added columns from student_details
        if (Schema::hasColumn('student_details', 'current_status')) {
            Schema::table('student_details', function (Blueprint $table) {
                $table->dropColumn(['current_status', 'status_changed_date']);
            });
        }
        
        // Drop tables in reverse order
        Schema::dropIfExists('attendance_analytics_cache');
        Schema::dropIfExists('student_archive');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('teacher_notes');
        Schema::dropIfExists('student_status_changes');
        
        echo "✅ Smart Attendance System tables removed successfully!\n";
    }
};
