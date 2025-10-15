<?php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixSF2GradeLevels extends Controller
{
    /**
     * Fix grade levels in submitted_sf2_reports table
     * This updates all existing records to use the correct grade_level from their sections
     */
    public function fixGradeLevels()
    {
        try {
            Log::info("Starting SF2 grade level fix...");
            
            // Get all submitted reports
            $reports = DB::table('submitted_sf2_reports')->get();
            
            $updated = 0;
            $errors = 0;
            
            foreach ($reports as $report) {
                // Get the section with its grade information
                $section = DB::table('sections')
                    ->join('grades', 'sections.grade_id', '=', 'grades.id')
                    ->where('sections.id', $report->section_id)
                    ->select('sections.*', 'grades.name as grade_name')
                    ->first();
                
                if ($section && $section->grade_name) {
                    // Update the report with correct grade level
                    DB::table('submitted_sf2_reports')
                        ->where('id', $report->id)
                        ->update([
                            'grade_level' => $section->grade_name,
                            'updated_at' => now()
                        ]);
                    
                    Log::info("Updated report #{$report->id}: {$report->section_name} from '{$report->grade_level}' to '{$section->grade_name}'");
                    $updated++;
                } else {
                    Log::warning("Could not find section or grade for report #{$report->id}, section_id: {$report->section_id}");
                    $errors++;
                }
            }
            
            Log::info("SF2 grade level fix completed. Updated: {$updated}, Errors: {$errors}");
            
            return response()->json([
                'success' => true,
                'message' => 'Grade levels updated successfully',
                'updated' => $updated,
                'errors' => $errors,
                'total' => count($reports)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error fixing SF2 grade levels: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fix grade levels',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
