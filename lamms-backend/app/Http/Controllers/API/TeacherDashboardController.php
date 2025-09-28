<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\TeacherSectionSubject;
use App\Services\AttendanceAnalyticsService;
use App\Models\AttendanceAnalyticsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TeacherDashboardController extends Controller
{
    protected AttendanceAnalyticsService $analyticsService;

    public function __construct(AttendanceAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get teacher dashboard data including profile, subjects, and attendance summary
     * Now uses optimized analytics with caching for better performance
     */
    public function getDashboardData($teacherId)
    {
        $startTime = microtime(true);
        
        try {
            Log::info("Starting dashboard data load for teacher ID: {$teacherId}");
            
            $teacher = Teacher::with(['user', 'sections.grade', 'subjects'])
                ->findOrFail($teacherId);

            // Get teacher's assigned subjects with section info
            $teacherSubjects = TeacherSectionSubject::with([
                'subject',
                'section.grade',
                'teacher'
            ])
            ->where('teacher_id', $teacherId)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                    'grade' => $assignment->section->grade->name ?? 'Unknown Grade',
                    'section' => $assignment->section->name ?? 'Unknown Section',
                    'originalSubject' => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name
                    ]
                ];
            });

            // Use optimized analytics service for better performance
            Log::info("Loading dashboard data for teacher ID: {$teacherId}");
            
            // Get comprehensive analytics using the smart analytics service
            $teacherAnalytics = $this->analyticsService->generateTeacherStudentAnalytics($teacherId);
            
            // Extract optimized data from analytics
            $attendanceSummary = $teacherAnalytics['summary'];
            $studentsWithIssues = collect($teacherAnalytics['students'])
                ->filter(function ($student) {
                    return in_array($student['risk_level'], ['high', 'critical']);
                })
                ->map(function ($student) {
                    return [
                        'id' => $student['student']['id'],
                        'name' => $student['student']['name'],
                        'gradeLevel' => $student['student']['grade'] ?? 'Unknown',
                        'section' => $student['student']['section'] ?? 'Unknown',
                        'absences' => $student['total_absences_this_year'],
                        'severity' => $student['risk_level'] === 'critical' ? 'critical' : 'warning'
                    ];
                })
                ->values()
                ->toArray();

            // Log performance metrics
            $this->logPerformance('Dashboard Data Load', $startTime, $teacherId);
            
            Log::info("Dashboard data loaded successfully for teacher {$teacherId}. Students with issues: " . count($studentsWithIssues));

            return response()->json([
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->user->name ?? 'Unknown Teacher',
                    'email' => $teacher->user->email ?? '',
                    'subjects' => $teacherSubjects->pluck('name')->unique()->values()
                ],
                'subjects' => $teacherSubjects,
                'attendanceSummary' => $attendanceSummary,
                'studentsWithIssues' => $studentsWithIssues,
                'performance' => [
                    'load_time_ms' => $this->logPerformance('Total Dashboard Load', $startTime, $teacherId),
                    'uses_analytics_cache' => true,
                    'indexed_queries' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add performance logging for monitoring
     */
    private function logPerformance($operation, $startTime, $teacherId)
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        Log::info("Teacher Dashboard Performance - {$operation}: {$executionTime}ms for teacher {$teacherId}");
        
        // Log if performance is slower than expected
        if ($executionTime > 100) { // More than 100ms
            Log::warning("Slow dashboard query detected - {$operation}: {$executionTime}ms for teacher {$teacherId}");
        }
        
        return $executionTime;
    }

    /**
     * Get attendance chart data for teacher's classes
     * Now uses optimized analytics with indexed queries
     */
    public function getAttendanceChartData($teacherId)
    {
        $startTime = microtime(true);
        
        try {
            Log::info("Loading chart data for teacher ID: {$teacherId}");
            
            // Get last 4 weeks of data
            $weeks = [];
            $attendanceData = [];
            $absentData = [];

            for ($i = 3; $i >= 0; $i--) {
                $startDate = Carbon::now()->subWeeks($i)->startOfWeek();
                $endDate = Carbon::now()->subWeeks($i)->endOfWeek();
                $weeks[] = $startDate->format('M j') . '-' . $endDate->format('j');

                // Use optimized query with proper indexing
                $weeklyStats = DB::table('attendances as a')
                    ->join('student_details as s', 'a.student_id', '=', 's.id')
                    ->join('sections as sec', 's.section_id', '=', 'sec.id')
                    ->join('teacher_section_subjects as tss', function($join) use ($teacherId) {
                        $join->on('tss.section_id', '=', 'sec.id')
                             ->where('tss.teacher_id', '=', $teacherId)
                             ->where('tss.is_active', '=', true);
                    })
                    ->whereBetween('a.date', [$startDate, $endDate])
                    ->where('s.current_status', 'active') // Only active students
                    ->select(
                        DB::raw('SUM(CASE WHEN a.status = "present" THEN 1 ELSE 0 END) as present_count'),
                        DB::raw('SUM(CASE WHEN a.status = "absent" THEN 1 ELSE 0 END) as absent_count')
                    )
                    ->first();

                $attendanceData[] = $weeklyStats->present_count ?? 0;
                $absentData[] = $weeklyStats->absent_count ?? 0;
            }

            // Log performance
            $this->logPerformance('Chart Data Load', $startTime, $teacherId);

            return response()->json([
                'labels' => $weeks,
                'datasets' => [
                    [
                        'label' => 'Present',
                        'data' => $attendanceData,
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                        'borderColor' => 'rgba(34, 197, 94, 1)',
                        'borderWidth' => 2,
                        'borderRadius' => 4,
                        'borderSkipped' => false,
                    ],
                    [
                        'label' => 'Absent',
                        'data' => $absentData,
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => 'rgba(239, 68, 68, 1)',
                        'borderWidth' => 2,
                        'borderRadius' => 4,
                        'borderSkipped' => false,
                    ]
                ],
                'performance' => [
                    'load_time_ms' => $this->logPerformance('Chart Data Generation', $startTime, $teacherId),
                    'uses_indexed_queries' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load chart data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
