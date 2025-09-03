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
        Schema::create('student_details', function (Blueprint $table) {
            $table->id();
            
            // Basic Info fields
            $table->string('studentId')->unique();
            $table->string('name')->nullable();
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('middleName')->nullable();
            $table->string('extensionName')->nullable();
            $table->string('email')->nullable();
            
            // Academic Info fields
            $table->string('gradeLevel')->nullable();
            $table->string('section')->nullable();
            $table->string('lrn')->nullable();
            $table->string('schoolYearStart')->nullable();
            $table->string('schoolYearEnd')->nullable();
            
            // Personal Info fields
            $table->string('gender')->nullable();
            $table->string('sex')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('birthplace')->nullable();
            $table->integer('age')->nullable();
            $table->string('psaBirthCertNo')->nullable();
            $table->string('motherTongue')->nullable();
            $table->string('profilePhoto')->nullable();
            
            // Address Info fields
            $table->json('currentAddress')->nullable();
            $table->json('permanentAddress')->nullable();
            
            // Contact Info fields
            $table->string('contactInfo')->nullable();
            
            // Parents Info fields
            $table->json('father')->nullable();
            $table->json('mother')->nullable();
            $table->string('parentName')->nullable();
            $table->string('parentContact')->nullable();
            
            // Status Info fields
            $table->string('status')->default('pending');
            $table->timestamp('enrollmentDate')->nullable();
            $table->timestamp('admissionDate')->nullable();
            $table->json('requirements')->nullable();
            
            // Additional Info fields
            $table->boolean('isIndigenous')->default(false);
            $table->string('indigenousCommunity')->nullable();
            $table->boolean('is4PsBeneficiary')->default(false);
            $table->string('householdID')->nullable();
            $table->boolean('hasDisability')->default(false);
            $table->json('disabilities')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_details');
    }
};
