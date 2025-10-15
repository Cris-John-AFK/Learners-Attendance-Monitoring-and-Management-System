<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Section;

class FixSF2SubmittedByTeacher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sf2:fix-submitted-by';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix submitted_by field in submitted_sf2_reports to use homeroom teacher ID instead of authenticated teacher ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to fix submitted_by field in SF2 reports...');
        
        try {
            // Get all submitted SF2 reports
            $reports = DB::table('submitted_sf2_reports')->get();
            
            $this->info("Found {$reports->count()} SF2 reports to process");
            
            $fixed = 0;
            $skipped = 0;
            $errors = 0;
            
            foreach ($reports as $report) {
                try {
                    // Get the section
                    $section = Section::find($report->section_id);
                    
                    if (!$section) {
                        $this->warn("Section not found for report ID {$report->id} (section_id: {$report->section_id})");
                        $skipped++;
                        continue;
                    }
                    
                    // Get the correct homeroom teacher ID
                    $correctTeacherId = $section->homeroom_teacher_id ?? $section->teacher_id;
                    
                    if (!$correctTeacherId) {
                        $this->warn("No homeroom teacher found for section {$section->name} (ID: {$section->id})");
                        $skipped++;
                        continue;
                    }
                    
                    // Check if it needs updating
                    if ($report->submitted_by != $correctTeacherId) {
                        // Get teacher names for logging
                        $oldTeacher = DB::table('teachers')->find($report->submitted_by);
                        $newTeacher = DB::table('teachers')->find($correctTeacherId);
                        
                        $oldName = $oldTeacher ? "{$oldTeacher->first_name} {$oldTeacher->last_name}" : "ID {$report->submitted_by}";
                        $newName = $newTeacher ? "{$newTeacher->first_name} {$newTeacher->last_name}" : "ID {$correctTeacherId}";
                        
                        $this->line("Fixing report ID {$report->id} for section '{$section->name}':");
                        $this->line("  Old: {$oldName}");
                        $this->line("  New: {$newName}");
                        
                        // Update the record
                        DB::table('submitted_sf2_reports')
                            ->where('id', $report->id)
                            ->update([
                                'submitted_by' => $correctTeacherId,
                                'updated_at' => now()
                            ]);
                        
                        $fixed++;
                    } else {
                        $skipped++;
                    }
                    
                } catch (\Exception $e) {
                    $this->error("Error processing report ID {$report->id}: " . $e->getMessage());
                    $errors++;
                }
            }
            
            $this->info("\n=== Summary ===");
            $this->info("Total reports: {$reports->count()}");
            $this->info("Fixed: {$fixed}");
            $this->info("Skipped (already correct or no teacher): {$skipped}");
            $this->info("Errors: {$errors}");
            
            if ($fixed > 0) {
                $this->info("\n✅ Successfully fixed {$fixed} SF2 report(s)!");
                $this->info("The notifications should now show the correct homeroom teacher names.");
            } else {
                $this->info("\n✅ All SF2 reports already have correct teacher assignments!");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Fatal error: " . $e->getMessage());
            Log::error("SF2 Fix Command Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
