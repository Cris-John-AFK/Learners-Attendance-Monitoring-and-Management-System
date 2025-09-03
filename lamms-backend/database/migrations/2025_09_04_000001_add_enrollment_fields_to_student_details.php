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
        Schema::table('student_details', function (Blueprint $table) {
            // Student Type & School Information
            if (!Schema::hasColumn('student_details', 'student_type')) {
                $table->string('student_type')->default('New')->after('id');
            }
            if (!Schema::hasColumn('student_details', 'school_year')) {
                $table->string('school_year')->default('2025-2026')->after('student_type');
            }
            if (!Schema::hasColumn('student_details', 'enrollment_id')) {
                $table->string('enrollment_id')->unique()->nullable()->after('school_year');
            }
            if (!Schema::hasColumn('student_details', 'religion')) {
                $table->string('religion')->nullable()->after('motherTongue');
            }

            // Address Information (detailed)
            if (!Schema::hasColumn('student_details', 'house_no')) {
                $table->string('house_no')->nullable()->after('address');
            }
            if (!Schema::hasColumn('student_details', 'street')) {
                $table->string('street')->nullable()->after('house_no');
            }
            if (!Schema::hasColumn('student_details', 'barangay')) {
                $table->string('barangay')->nullable()->after('street');
            }
            if (!Schema::hasColumn('student_details', 'city_municipality')) {
                $table->string('city_municipality')->nullable()->after('barangay');
            }
            if (!Schema::hasColumn('student_details', 'province')) {
                $table->string('province')->nullable()->after('city_municipality');
            }
            if (!Schema::hasColumn('student_details', 'country')) {
                $table->string('country')->default('Philippines')->after('province');
            }
            if (!Schema::hasColumn('student_details', 'zip_code')) {
                $table->string('zip_code')->nullable()->after('country');
            }

            // Parent/Guardian Information
            if (!Schema::hasColumn('student_details', 'father_name')) {
                $table->string('father_name')->nullable()->after('father');
            }
            if (!Schema::hasColumn('student_details', 'father_occupation')) {
                $table->string('father_occupation')->nullable()->after('father_name');
            }
            if (!Schema::hasColumn('student_details', 'father_contact')) {
                $table->string('father_contact')->nullable()->after('father_occupation');
            }
            if (!Schema::hasColumn('student_details', 'father_education')) {
                $table->string('father_education')->nullable()->after('father_contact');
            }
            if (!Schema::hasColumn('student_details', 'mother_name')) {
                $table->string('mother_name')->nullable()->after('mother');
            }
            if (!Schema::hasColumn('student_details', 'mother_occupation')) {
                $table->string('mother_occupation')->nullable()->after('mother_name');
            }
            if (!Schema::hasColumn('student_details', 'mother_contact')) {
                $table->string('mother_contact')->nullable()->after('mother_occupation');
            }
            if (!Schema::hasColumn('student_details', 'mother_education')) {
                $table->string('mother_education')->nullable()->after('mother_contact');
            }
            if (!Schema::hasColumn('student_details', 'guardian_name')) {
                $table->string('guardian_name')->nullable()->after('mother_education');
            }
            if (!Schema::hasColumn('student_details', 'guardian_occupation')) {
                $table->string('guardian_occupation')->nullable()->after('guardian_name');
            }
            if (!Schema::hasColumn('student_details', 'guardian_contact')) {
                $table->string('guardian_contact')->nullable()->after('guardian_occupation');
            }
            if (!Schema::hasColumn('student_details', 'guardian_address')) {
                $table->string('guardian_address')->nullable()->after('guardian_contact');
            }

            // Previous School Information
            if (!Schema::hasColumn('student_details', 'last_grade_completed')) {
                $table->string('last_grade_completed')->nullable()->after('guardian_address');
            }
            if (!Schema::hasColumn('student_details', 'last_school_year')) {
                $table->string('last_school_year')->nullable()->after('last_grade_completed');
            }
            if (!Schema::hasColumn('student_details', 'last_school_attended')) {
                $table->string('last_school_attended')->nullable()->after('last_school_year');
            }
            if (!Schema::hasColumn('student_details', 'last_school_address')) {
                $table->string('last_school_address')->nullable()->after('last_school_attended');
            }

            // Contact & Additional Information
            if (!Schema::hasColumn('student_details', 'household_income')) {
                $table->string('household_income')->default('Below 10k')->after('last_school_address');
            }
            if (!Schema::hasColumn('student_details', 'enrollment_status')) {
                $table->string('enrollment_status')->default('Enrolled')->after('household_income');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropColumn([
                'student_type', 'school_year', 'enrollment_id', 'religion',
                'house_no', 'street', 'barangay', 'city_municipality', 'province', 'country', 'zip_code',
                'father_name', 'father_occupation', 'father_contact', 'father_education',
                'mother_name', 'mother_occupation', 'mother_contact', 'mother_education',
                'guardian_name', 'guardian_occupation', 'guardian_contact', 'guardian_address',
                'last_grade_completed', 'last_school_year', 'last_school_attended', 'last_school_address',
                'household_income', 'enrollment_status'
            ]);
        });
    }
};
