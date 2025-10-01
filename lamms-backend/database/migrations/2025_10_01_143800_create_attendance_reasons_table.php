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
        Schema::create('attendance_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('reason_name');
            $table->enum('reason_type', ['late', 'excused']); // Which attendance status this reason applies to
            $table->string('category')->nullable(); // e.g., 'Health', 'Transportation', 'Family', 'Weather', 'Other'
            $table->integer('display_order')->default(0); // For UI ordering
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['reason_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_reasons');
    }
};
