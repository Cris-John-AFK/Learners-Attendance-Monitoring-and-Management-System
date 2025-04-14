<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add indexes to curriculum_grade table
        Schema::table('curriculum_grade', function (Blueprint $table) {
            $table->index('curriculum_id');
            $table->index('grade_id');
        });

        // Add indexes to curriculum_grade_subject table
        Schema::table('curriculum_grade_subject', function (Blueprint $table) {
            $table->index('curriculum_id');
            $table->index('grade_id');
            $table->index('subject_id');
            $table->index('status');
            $table->index(['curriculum_id', 'grade_id', 'status']);
        });
    }

    public function down()
    {
        // Remove indexes from curriculum_grade table
        Schema::table('curriculum_grade', function (Blueprint $table) {
            $table->dropIndex(['curriculum_id']);
            $table->dropIndex(['grade_id']);
        });

        // Remove indexes from curriculum_grade_subject table
        Schema::table('curriculum_grade_subject', function (Blueprint $table) {
            $table->dropIndex(['curriculum_id']);
            $table->dropIndex(['grade_id']);
            $table->dropIndex(['subject_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['curriculum_id', 'grade_id', 'status']);
        });
    }
};
