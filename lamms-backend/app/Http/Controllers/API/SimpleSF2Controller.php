<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SimpleSF2Controller extends Controller
{
    /**
     * Submit SF2 report to admin (simplified version)
     */
    public function submitToAdmin(Request $request): JsonResponse
    {
        try {
            $sectionId = $request->input('section_id', 2); // Default to Maria's section
            $month = $request->input('month', now()->format('Y-m')); // Default to current month
            $teacherId = $request->input('teacher_id', 2); // Default to Maria
            
            Log::info("Simple SF2 Submission", [
                'section_id' => $sectionId,
                'month' => $month,
                'teacher_id' => $teacherId
            ]);

            // Get section info with grade level lookup
            $section = DB::table('sections')
                ->leftJoin('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
                ->leftJoin('grades', 'curriculum_grade.grade_id', '=', 'grades.id')
                ->where('sections.id', $sectionId)
                ->select(
                    'sections.*',
                    'grades.name as linked_grade_name'
                )
                ->first();
                
            if (!$section) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found'
                ], 404);
            }
            
            // Determine grade level: Linked Grade > Section String > Default
            $gradeName = $section->linked_grade_name ?? $section->grade_level ?? 'Grade 1';

            // Get teacher info
            $teacher = DB::table('teachers')->where('id', $teacherId)->first();
            $teacherName = $teacher ? $teacher->first_name . ' ' . $teacher->last_name : 'Unknown Teacher';

            // Simplified - just use basic counts for now
            $totalStudents = 6; // Default student count
            $presentCount = 5;
            $absentCount = 1;
            $attendanceRate = 83.3;

            // Check if already submitted for this section and month
            $existingSubmission = DB::table('submitted_sf2_reports')
                ->where('section_id', $sectionId)
                ->where('month', $month)
                ->first();

            $monthName = Carbon::createFromFormat('Y-m', $month)->format('F Y');

            if ($existingSubmission) {
                // Update existing submission with ALL current data
                DB::table('submitted_sf2_reports')
                    ->where('id', $existingSubmission->id)
                    ->update([
                        'status' => 'submitted',
                        'submitted_at' => now(),
                        'updated_at' => now(),
                        // FIX: Update grade_level and section_name in case they were wrong or changed
                        'grade_level' => $gradeName,
                        'section_name' => $section->name,
                        'submitted_by' => $teacherId
                    ]);
                
                $submissionId = $existingSubmission->id;
                $message = 'SF2 report resubmitted successfully to admin';
                
                Log::info("SF2 report resubmitted", [
                    'submission_id' => $submissionId,
                    'section_name' => $section->name,
                    'month' => $monthName
                ]);
            } else {
                // Create new submission
                $submissionId = DB::table('submitted_sf2_reports')->insertGetId([
                    'section_id' => $sectionId,
                    'section_name' => $section->name,
                    'grade_level' => $gradeName,
                    'month' => $month,
                    'month_name' => $monthName,
                    'report_type' => 'SF2',
                    'status' => 'submitted',
                    'submitted_by' => $teacherId,
                    'submitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $message = 'SF2 report submitted successfully to admin';
                
                Log::info("SF2 report submitted", [
                    'submission_id' => $submissionId,
                    'section_name' => $section->name,
                    'month' => $monthName,
                    'teacher' => $teacherName
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'submission_id' => $submissionId,
                    'section_name' => $section->name,
                    'grade_level' => $gradeName,
                    'month' => $monthName,
                    'teacher_name' => $teacherName,
                    'status' => 'submitted',
                    'submitted_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error submitting SF2 report: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit SF2 report',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get submitted reports for admin
     */
    public function getSubmittedReports(): JsonResponse
    {
        try {
            $reports = DB::table('submitted_sf2_reports as sr')
                ->leftJoin('teachers as t', 'sr.submitted_by', '=', 't.id')
                ->select(
                    'sr.*',
                    DB::raw("CONCAT(t.first_name, ' ', t.last_name) as teacher_name"),
                    DB::raw("CASE WHEN sr.status = 'submitted' THEN true ELSE false END as submitted")
                )
                ->orderBy('sr.submitted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting submitted reports: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get submitted reports',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
