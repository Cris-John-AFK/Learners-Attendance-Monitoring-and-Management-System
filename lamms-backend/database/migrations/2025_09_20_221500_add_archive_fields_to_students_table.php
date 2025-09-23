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
        Schema::table('students', function (Blueprint $table) {
            // Add archive-related fields
            $table->string('archive_reason')->nullable()->after('status');
            $table->text('archive_notes')->nullable()->after('archive_reason');
            $table->timestamp('archived_at')->nullable()->after('archive_notes');
            $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
            
            // Add index for better performance on archived students queries
            $table->index(['status', 'archived_at']);
            
            // Foreign key for who archived the student (admin user)
            $table->foreign('archived_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropIndex(['status', 'archived_at']);
            $table->dropColumn(['archive_reason', 'archive_notes', 'archived_at', 'archived_by']);
        });
    }
};
