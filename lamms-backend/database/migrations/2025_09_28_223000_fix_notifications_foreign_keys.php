<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop any foreign key constraints on notifications table
        try {
            // Get all foreign key constraints on notifications table
            $constraints = DB::select("
                SELECT conname 
                FROM pg_constraint 
                WHERE contype = 'f' 
                AND conrelid = 'notifications'::regclass
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS {$constraint->conname}");
                echo "Dropped constraint: {$constraint->conname}\n";
            }
            
        } catch (\Exception $e) {
            // Continue if there are no constraints to drop
            echo "No foreign key constraints found or error dropping: " . $e->getMessage() . "\n";
        }
        
        // Ensure the table structure is correct without foreign key constraints
        Schema::table('notifications', function (Blueprint $table) {
            // Make sure columns exist and are properly typed
            if (!Schema::hasColumn('notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id');
            }
            if (!Schema::hasColumn('notifications', 'related_student_id')) {
                $table->unsignedBigInteger('related_student_id')->nullable();
            }
            if (!Schema::hasColumn('notifications', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't want to re-add the foreign key constraints as they cause issues
        // Leave the table structure as is
    }
};
