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
        // Skip if table already exists
        if (Schema::hasTable('attendance_sessions')) {
            return;
        }
        
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->date('session_date');
            $table->time('session_start_time');
            $table->time('session_end_time')->nullable();
            $table->enum('session_type', ['regular', 'makeup', 'special'])->default('regular');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Ensure one session per subject per section per date
            $table->unique(['section_id', 'subject_id', 'session_date'], 'unique_session_per_day');
            
            // Indexes for performance
            $table->index(['teacher_id', 'session_date']);
            $table->index(['section_id', 'session_date']);
            $table->index(['status', 'session_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
