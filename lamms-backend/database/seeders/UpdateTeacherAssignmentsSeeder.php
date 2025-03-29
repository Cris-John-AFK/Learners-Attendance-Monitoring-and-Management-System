<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTeacherAssignmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Get all assignments that have role='primary'
            $primaryAssignments = DB::table('teacher_section_subject')
                ->where('role', 'primary')
                ->get();
            
            // Log the count of primary assignments found
            Log::info('Found ' . count($primaryAssignments) . ' primary teacher assignments to update');
            
            // Update each primary assignment to have is_primary=true
            foreach ($primaryAssignments as $assignment) {
                DB::table('teacher_section_subject')
                    ->where('id', $assignment->id)
                    ->update(['is_primary' => true]);
                
                Log::info('Updated assignment #' . $assignment->id . ' to have is_primary=true');
            }
            
            // Update any non-primary assignments with is_primary=true to have role='primary'
            $inconsistentAssignments = DB::table('teacher_section_subject')
                ->where('is_primary', true)
                ->where('role', '!=', 'primary')
                ->get();
                
            // Log the count of inconsistent assignments found
            Log::info('Found ' . count($inconsistentAssignments) . ' inconsistent assignments (is_primary=true but role!=primary)');
            
            // Update each inconsistent assignment
            foreach ($inconsistentAssignments as $assignment) {
                DB::table('teacher_section_subject')
                    ->where('id', $assignment->id)
                    ->update(['role' => 'primary']);
                
                Log::info('Updated assignment #' . $assignment->id . ' to have role=primary');
            }
            
            // Notify completion
            $this->command->info('Successfully updated primary teacher assignments.');
        } catch (\Exception $e) {
            Log::error('Error updating teacher assignments: ' . $e->getMessage());
            $this->command->error('Error updating teacher assignments: ' . $e->getMessage());
        }
    }
}
