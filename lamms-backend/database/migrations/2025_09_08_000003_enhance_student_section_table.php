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
        Schema::table('student_section', function (Blueprint $table) {
            $table->date('enrollment_date')->default(now())->after('school_year');
            $table->enum('status', ['enrolled', 'transferred', 'dropped'])->default('enrolled')->after('enrollment_date');
            
            // Add index for better performance
            $table->index(['status', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_section', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_active']);
            $table->dropColumn(['enrollment_date', 'status']);
        });
    }
};
