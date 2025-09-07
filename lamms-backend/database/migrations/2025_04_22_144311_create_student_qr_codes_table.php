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
        Schema::create('student_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('qr_code_data')->unique(); // The actual QR code content
            $table->string('qr_code_hash')->unique(); // Hash for security
            $table->boolean('is_active')->default(true);
            $table->timestamp('generated_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('student_details')->onDelete('cascade');
            $table->index(['student_id', 'is_active']);
            $table->index('qr_code_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_qr_codes');
    }
};
