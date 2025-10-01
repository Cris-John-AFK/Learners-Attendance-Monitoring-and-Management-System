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
        Schema::create('student_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('previous_status');
            $table->string('new_status');
            $table->string('reason')->nullable();
            $table->string('reason_category')->nullable(); // 'domestic', 'individual', 'school', 'geographical'
            $table->date('effective_date');
            $table->unsignedBigInteger('changed_by_teacher_id');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
            $table->foreign('changed_by_teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            
            // Indexes
            $table->index(['student_id', 'created_at']);
            $table->index('changed_by_teacher_id');
        });

        // Add new columns to student_details table
        Schema::table('student_details', function (Blueprint $table) {
            $table->string('enrollment_status')->default('active')->after('status');
            // active, dropped_out, transferred_out, transferred_in
            $table->string('dropout_reason')->nullable()->after('enrollment_status');
            $table->string('dropout_reason_category')->nullable()->after('dropout_reason');
            $table->date('status_effective_date')->nullable()->after('dropout_reason_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropColumn(['enrollment_status', 'dropout_reason', 'dropout_reason_category', 'status_effective_date']);
        });
        
        Schema::dropIfExists('student_status_history');
    }
};
