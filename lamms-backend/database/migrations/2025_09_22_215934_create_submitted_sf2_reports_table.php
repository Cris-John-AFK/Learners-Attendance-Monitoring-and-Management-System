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
        Schema::create('submitted_sf2_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->string('section_name');
            $table->string('grade_level');
            $table->string('month'); // Format: Y-m (e.g., 2025-09)
            $table->string('month_name'); // Format: September 2025
            $table->string('report_type')->default('SF2');
            $table->enum('status', ['submitted', 'reviewed', 'approved', 'rejected'])->default('submitted');
            $table->unsignedBigInteger('submitted_by'); // teacher_id
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable(); // admin_id
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
            $table->foreign('submitted_by')->references('id')->on('teachers')->onDelete('cascade');
            
            // Indexes
            $table->index(['section_id', 'month']);
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submitted_sf2_reports');
    }
};
