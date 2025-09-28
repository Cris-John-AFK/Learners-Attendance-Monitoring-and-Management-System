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
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('type', 50);
                $table->string('title');
                $table->text('message');
                $table->json('data')->nullable();
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->unsignedBigInteger('related_student_id')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                // Add indexes for performance
                $table->index(['user_id', 'is_read'], 'idx_user_read');
                $table->index(['user_id', 'created_at'], 'idx_user_created');
                $table->index(['type', 'user_id'], 'idx_type_user');
                $table->index(['priority', 'user_id'], 'idx_priority_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
