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
        if (Schema::hasTable('attendance_records')) {
            return;
        }
        
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('student_details', 'id')->onDelete('cascade');
            $table->foreignId('attendance_status_id')->constrained('attendance_statuses')->onDelete('restrict');
            $table->timestamp('marked_at')->useCurrent();
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->constrained('teachers')->onDelete('restrict');
            $table->timestamps();

            // Ensure one record per student per session
            $table->unique(['session_id', 'student_id']);
            
            // Indexes for performance
            $table->index(['student_id', 'marked_at']);
            $table->index(['attendance_status_id', 'marked_at']);
            $table->index(['session_id', 'attendance_status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
