<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production-ready attendance system migration
     * Designed for multi-user school environment with data integrity
     */
    public function up(): void
    {
        // 1. Attendance Sessions - Track each attendance taking session
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->onDelete('set null');
            $table->date('session_date');
            $table->time('session_start_time');
            $table->time('session_end_time')->nullable();
            $table->enum('session_type', ['regular', 'makeup', 'special'])->default('regular');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('metadata')->nullable(); // Store additional session info
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->timestamp('completed_at')->nullable();
            
            // Ensure uniqueness: one active session per teacher-section-subject-date
            $table->unique(['teacher_id', 'section_id', 'subject_id', 'session_date', 'status'], 'unique_active_session');
            
            // Indexes for performance
            $table->index(['session_date', 'status']);
            $table->index(['teacher_id', 'session_date']);
            $table->index(['section_id', 'session_date']);
        });

        // 2. Enhanced Attendance Records with audit trail
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('student_details')->onDelete('cascade');
            $table->foreignId('attendance_status_id')->constrained('attendance_statuses')->onDelete('restrict');
            $table->foreignId('marked_by_teacher_id')->constrained('teachers')->onDelete('restrict');
            
            // Timing information
            $table->timestamp('marked_at');
            $table->time('arrival_time')->nullable(); // When student actually arrived
            $table->time('departure_time')->nullable(); // When student left (if applicable)
            
            // Additional information
            $table->text('remarks')->nullable();
            $table->enum('marking_method', ['manual', 'qr_scan', 'auto', 'bulk'])->default('manual');
            $table->ipAddress('marked_from_ip')->nullable();
            $table->json('location_data')->nullable(); // GPS/location if available
            
            // Verification and approval workflow
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by_teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes(); // For audit trail - never truly delete attendance
            
            // Constraints
            $table->unique(['attendance_session_id', 'student_id'], 'unique_student_session_attendance');
            
            // Indexes for performance and reporting
            $table->index(['student_id', 'marked_at']);
            $table->index(['attendance_session_id', 'attendance_status_id']);
            $table->index(['marked_by_teacher_id', 'marked_at']);
            $table->index(['is_verified', 'verified_at']);
        });

        // 3. Attendance Modifications/Corrections Log
        Schema::create('attendance_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->onDelete('cascade');
            $table->foreignId('modified_by_teacher_id')->constrained('teachers')->onDelete('restrict');
            
            // What was changed
            $table->json('old_values'); // Store previous values
            $table->json('new_values'); // Store new values
            $table->enum('modification_type', ['status_change', 'time_correction', 'remarks_update', 'verification'])->default('status_change');
            $table->text('reason'); // Why was it modified
            
            // Authorization
            $table->foreignId('authorized_by_teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
            $table->timestamp('authorized_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['attendance_record_id', 'created_at']);
            $table->index(['modified_by_teacher_id', 'created_at']);
        });

        // 4. Teacher-Section-Subject Schedule (for attendance validation)
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            
            // Schedule details
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            
            // Semester/Term information
            $table->string('school_year', 20); // e.g., "2025-2026"
            $table->enum('semester', ['1st', '2nd', 'summer'])->default('1st');
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Constraints
            $table->unique([
                'teacher_id', 'section_id', 'subject_id', 'day_of_week', 
                'school_year', 'semester', 'effective_from'
            ], 'unique_class_schedule');
            
            // Indexes
            $table->index(['day_of_week', 'start_time', 'is_active']);
            $table->index(['school_year', 'semester', 'is_active']);
        });

        // 5. Attendance Policy Rules
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name');
            $table->enum('scope', ['school_wide', 'grade_level', 'section', 'subject'])->default('school_wide');
            $table->foreignId('scope_id')->nullable(); // grade_id, section_id, or subject_id
            
            // Policy rules
            $table->integer('late_threshold_minutes')->default(15); // When to mark as late
            $table->integer('absent_threshold_minutes')->default(30); // When to mark as absent
            $table->boolean('allow_teacher_override')->default(true);
            $table->boolean('require_verification')->default(false);
            $table->json('allowed_statuses'); // Which statuses can be used
            
            // Effective period
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['scope', 'scope_id', 'is_active']);
            $table->index(['effective_from', 'effective_until', 'is_active']);
        });

        // 6. Student Enrollment History (for accurate attendance tracking)
        Schema::create('student_enrollment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_details')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->date('enrolled_date');
            $table->date('unenrolled_date')->nullable();
            $table->enum('enrollment_status', ['active', 'transferred', 'dropped', 'graduated'])->default('active');
            $table->string('school_year', 20);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Constraints - student can only be in one active enrollment per school year
            $table->unique(['student_id', 'school_year', 'enrollment_status'], 'unique_active_enrollment');
            
            // Indexes
            $table->index(['student_id', 'enrolled_date', 'unenrolled_date']);
            $table->index(['section_id', 'school_year', 'enrollment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollment_history');
        Schema::dropIfExists('attendance_policies');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('attendance_modifications');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};