<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGuardhouseAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guardhouse_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('qr_code_data');
            $table->enum('record_type', ['check-in', 'check-out']);
            $table->timestamp('timestamp');
            $table->date('date');
            $table->string('guard_name')->default('Guard');
            $table->string('guard_id')->default('G-001');
            $table->boolean('is_manual')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
            
            // Indexes for performance
            $table->index('student_id');
            $table->index('date');
            $table->index('record_type');
            $table->index(['student_id', 'date']);
            $table->index(['date', 'record_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardhouse_attendance');
    }
}
