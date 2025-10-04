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

            // Get section info
            $section = DB::table('sections')->where('id', $sectionId)->first();
            if (!$section) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found'
                ], 404);
            }

            // Get teacher info
            $teacher = DB::table('teachers')->where('id', $teacherId)->first();
            $teacherName = $teacher ? $teacher->first_name . ' ' . $teacher->last_name : 'Unknown Teacher';

            // Get real attendance statistics for this section
            $totalStudents = DB::table('student_section as ss')
                ->join('students as s', 'ss.student_id', '=', 's.id')
                ->where('ss.section_id', $sectionId)
                ->where('ss.is_active', true)
                ->where('s.status', 'Enrolled')
                ->count();

            // Get attendance data for the current month
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            
            // Calculate attendance statistics
            $attendanceStats = DB::table('attendance_sessions as ases')
                ->join('attendance_records as ar', 'ases.id', '=', 'ar.session_id')
                ->join('attendance_statuses as ast', 'ar.status_id', '=', 'ast.id')
                ->where('ases.section_id', $sectionId)
                ->where('ases.teacher_id', $teacherId)
                ->whereBetween('ases.session_date', [$monthStart, $monthEnd])
                ->select(
                    DB::raw('COUNT(CASE WHEN LOWER(ast.status_name) IN (\'present\', \'late\', \'tardy\') THEN 1 END) as total_present'),
                    DB::raw('COUNT(CASE WHEN LOWER(ast.status_name) = \'absent\' THEN 1 END) as total_absent'),
                    DB::raw('COUNT(*) as total_records')
                )
                ->first();

            $presentCount = $attendanceStats->total_present ?? 0;
            $absentCount = $attendanceStats->total_absent ?? 0;
            $totalRecords = $attendanceStats->total_records ?? 0;
            
            // Calculate attendance rate
            $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0;

            // Check if already submitted for this section and month
            $existingSubmission = DB::table('submitted_sf2_reports')
                ->where('section_id', $sectionId)
                ->where('month', $month)
                ->first();

            $monthName = Carbon::createFromFormat('Y-m', $month)->format('F Y');

            if ($existingSubmission) {
                // Update existing submission with real data
                DB::table('submitted_sf2_reports')
                    ->where('id', $existingSubmission->id)
                    ->update([
                        'status' => 'submitted',
                        'submitted_at' => now(),
                        'updated_at' => now(),
                        'total_students' => $totalStudents,
                        'present_today' => $presentCount,
                        'absent_today' => $absentCount,
                        'attendance_rate' => $attendanceRate
                    ]);
                
                $submissionId = $existingSubmission->id;
                $message = 'SF2 report resubmitted successfully to admin';
                
                Log::info("SF2 report resubmitted", [
                    'submission_id' => $submissionId,
                    'section_name' => $section->name,
                    'month' => $monthName
                ]);
            } else {
                // Create new submission with real data
                $submissionId = DB::table('submitted_sf2_reports')->insertGetId([
                    'section_id' => $sectionId,
                    'section_name' => $section->name,
                    'grade_level' => 'Grade 1',
                    'month' => $month,
                    'month_name' => $monthName,
                    'report_type' => 'SF2',
                    'status' => 'submitted',
                    'submitted_by' => $teacherId,
                    'submitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'total_students' => $totalStudents,
                    'present_today' => $presentCount,
                    'absent_today' => $absentCount,
                    'attendance_rate' => $attendanceRate
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
                    'grade_level' => 'Grade 1',
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
