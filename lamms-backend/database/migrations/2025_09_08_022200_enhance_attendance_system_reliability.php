<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enhance attendance system for maximum reliability and future scalability
     */
    public function up(): void
    {
        // 1. Add session versioning and editing capabilities
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('status');
            $table->foreignId('original_session_id')->nullable()->constrained('attendance_sessions')->onDelete('set null')->after('version');
            $table->enum('edit_reason', ['correction', 'late_entry', 'system_error', 'administrative'])->nullable()->after('original_session_id');
            $table->text('edit_notes')->nullable()->after('edit_reason');
            $table->foreignId('edited_by_teacher_id')->nullable()->constrained('teachers')->onDelete('set null')->after('edit_notes');
            $table->timestamp('edited_at')->nullable()->after('edited_by_teacher_id');
            $table->boolean('is_current_version')->default(true)->after('edited_at');
            
            // Add indexes for performance
            $table->index(['original_session_id', 'version']);
            $table->index(['is_current_version', 'status']);
            $table->index(['edited_by_teacher_id', 'edited_at']);
        });

        // 2. Create session edit history table for detailed audit trail
        Schema::create('attendance_session_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->foreignId('edited_by_teacher_id')->constrained('teachers')->onDelete('restrict');
            $table->json('changes'); // Store what was changed (old vs new values)
            $table->enum('edit_type', ['session_data', 'attendance_records', 'status_change', 'time_correction']);
            $table->enum('edit_reason', ['correction', 'late_entry', 'system_error', 'administrative']);
            $table->text('notes')->nullable();
            $table->ipAddress('edited_from_ip')->nullable();
            $table->json('metadata')->nullable(); // Store additional context
            $table->timestamps();
            
            // Indexes
            $table->index(['session_id', 'created_at']);
            $table->index(['edited_by_teacher_id', 'created_at']);
            $table->index(['edit_type', 'edit_reason']);
        });

        // 3. Add data integrity constraints and validation
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('is_verified');
            $table->foreignId('original_record_id')->nullable()->constrained('attendance_records')->onDelete('set null')->after('version');
            $table->boolean('is_current_version')->default(true)->after('original_record_id');
            $table->enum('data_source', ['manual', 'qr_scan', 'bulk_import', 'system_generated'])->default('manual')->after('marking_method');
            $table->json('validation_metadata')->nullable()->after('data_source'); // Store validation info
            
            // Add indexes
            $table->index(['original_record_id', 'version']);
            $table->index(['is_current_version', 'attendance_session_id']);
            $table->index(['data_source', 'marked_at']);
        });

        // 4. Create comprehensive audit log table
        Schema::create('attendance_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // 'session', 'record', 'modification'
            $table->unsignedBigInteger('entity_id');
            $table->enum('action', ['create', 'update', 'delete', 'complete', 'edit', 'verify']);
            $table->foreignId('performed_by_teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable(); // Additional context data
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['performed_by_teacher_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        // 5. Add session statistics cache table for performance
        Schema::create('attendance_session_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->integer('total_students');
            $table->integer('marked_students');
            $table->integer('present_count');
            $table->integer('absent_count');
            $table->integer('late_count');
            $table->integer('excused_count');
            $table->decimal('attendance_rate', 5, 2); // Percentage
            $table->json('detailed_stats')->nullable(); // Additional statistics
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            // Unique constraint - one stats record per session
            $table->unique('session_id');
            $table->index(['attendance_rate', 'calculated_at']);
        });

        // 6. Create data validation rules table
        Schema::create('attendance_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->enum('rule_type', ['time_validation', 'status_validation', 'duplicate_check', 'business_logic']);
            $table->json('rule_config'); // Store rule parameters
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100); // Lower number = higher priority
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['rule_type', 'is_active', 'priority']);
        });

        // 7. Add performance indexes for common queries (without CONCURRENTLY in migration)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_sessions_teacher_date_status 
                      ON attendance_sessions (teacher_id, session_date, status)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_session_student_status 
                      ON attendance_records (attendance_session_id, student_id, attendance_status_id)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_attendance_records_marked_at_status 
                      ON attendance_records (marked_at, attendance_status_id) WHERE is_current_version = true');

        // 8. Insert default validation rules
        DB::table('attendance_validation_rules')->insert([
            [
                'rule_name' => 'prevent_future_attendance',
                'rule_type' => 'time_validation',
                'rule_config' => json_encode(['max_future_days' => 0]),
                'is_active' => true,
                'priority' => 10,
                'description' => 'Prevent marking attendance for future dates',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'rule_name' => 'prevent_duplicate_records',
                'rule_type' => 'duplicate_check',
                'rule_config' => json_encode(['check_fields' => ['student_id', 'session_id']]),
                'is_active' => true,
                'priority' => 20,
                'description' => 'Prevent duplicate attendance records for same student in same session',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'rule_name' => 'validate_session_time_range',
                'rule_type' => 'time_validation',
                'rule_config' => json_encode(['max_session_hours' => 8, 'min_session_minutes' => 5]),
                'is_active' => true,
                'priority' => 30,
                'description' => 'Validate session duration is within reasonable limits',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes first
        DB::statement('DROP INDEX IF EXISTS idx_attendance_sessions_teacher_date_status');
        DB::statement('DROP INDEX IF EXISTS idx_attendance_records_session_student_status');
        DB::statement('DROP INDEX IF EXISTS idx_attendance_records_marked_at_status');
        
        // Drop tables in reverse order
        Schema::dropIfExists('attendance_validation_rules');
        Schema::dropIfExists('attendance_session_stats');
        Schema::dropIfExists('attendance_audit_log');
        Schema::dropIfExists('attendance_session_edits');
        
        // Remove columns from existing tables
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'version', 'original_record_id', 'is_current_version', 
                'data_source', 'validation_metadata'
            ]);
        });
        
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'version', 'original_session_id', 'edit_reason', 'edit_notes',
                'edited_by_teacher_id', 'edited_at', 'is_current_version'
            ]);
        });
    }
};
