<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGuardhouseArchiveTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create guardhouse archive sessions table
        Schema::create('guardhouse_archive_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->integer('total_records')->default(0);
            $table->timestamp('archived_at');
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('session_date');
            $table->index('archived_at');
        });
        
        // Create guardhouse archived records table
        Schema::create('guardhouse_archived_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('student_id')->nullable();
            $table->string('student_name');
            $table->string('grade_level')->nullable();
            $table->string('section')->nullable();
            $table->enum('record_type', ['check-in', 'check-out']);
            $table->timestamp('timestamp');
            $table->date('session_date');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('session_id');
            $table->index('student_id');
            $table->index('student_name');
            $table->index('session_date');
            $table->index('record_type');
            $table->index(['session_date', 'record_type']);
            $table->index(['student_id', 'session_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardhouse_archived_records');
        Schema::dropIfExists('guardhouse_archive_sessions');
    }
}
