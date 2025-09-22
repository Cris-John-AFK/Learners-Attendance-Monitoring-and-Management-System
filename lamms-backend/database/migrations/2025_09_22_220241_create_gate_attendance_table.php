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
        Schema::create('gate_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('student_qr_code')->nullable();
            $table->enum('type', ['check_in', 'check_out'])->default('check_in');
            $table->timestamp('scan_time');
            $table->date('scan_date');
            $table->string('gate_location')->default('main_gate');
            $table->string('scanner_device')->nullable();
            $table->json('metadata')->nullable(); // For additional data like IP, device info, etc.
            $table->boolean('is_valid')->default(true);
            $table->string('remarks')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['student_id', 'scan_date']);
            $table->index(['scan_date', 'type']);
            $table->index('scan_time');
            
            // Foreign key constraint
            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_attendance');
    }
};
