<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AttendanceAnalyticsService;
use App\Models\AttendanceAnalyticsCache;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SmartAttendanceAnalyticsController extends Controller
{
    protected AttendanceAnalyticsService $analyticsService;

    public function __construct(AttendanceAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive analytics for a specific student
     * 
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStudentAnalytics(int $studentId): JsonResponse
    {
        try {
            Log::info("Generating analytics for student ID: {$studentId}");

            // Verify student exists and is active
            $student = Student::where('id', $studentId)
                ->where('current_status', 'active')
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found or not active'
                ], 404);
            }

            // Generate comprehensive analytics
            $analytics = $this->analyticsService->generateStudentAnalytics($studentId);

            Log::info("Analytics generated successfully for student: {$student->firstName} {$student->lastName}");

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Student analytics generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error generating student analytics: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate student analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get analytics for all students assigned to a teacher
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTeacherStudentAnalytics(Request $request): JsonResponse
    {
        try {
            // Get teacher ID from authenticated user or request
            $teacherId = $request->input('teacher_id');
            
            if (!$teacherId && Auth::check()) {
                $user = Auth::user();
                $teacher = Teacher::where('user_id', $user->id)->first();
                $teacherId = $teacher ? $teacher->id : null;
            }

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID is required'
                ], 400);
            }

            Log::info("Generating analytics for teacher ID: {$teacherId}");

            // Verify teacher exists
            $teacher = Teacher::find($teacherId);
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found'
                ], 404);
            }

            // Generate analytics for all teacher's students
            $analytics = $this->analyticsService->generateTeacherStudentAnalytics($teacherId);

            Log::info("Teacher analytics generated successfully for {$analytics['summary']['total_students']} students");

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->first_name . ' ' . $teacher->last_name
                ],
                'message' => 'Teacher student analytics generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error generating teacher analytics: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate teacher analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get students exceeding 18 absence limit (critical cases)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getCriticalAbsenteeism(Request $request): JsonResponse
    {
        try {
            $teacherId = $request->input('teacher_id');
            
            // Check cache first
            $cacheKey = "critical_absenteeism_teacher_{$teacherId}_" . now()->format('Y-m-d');
            $cached = cache()->get($cacheKey);
            
            if ($cached) {
                Log::info("Returning cached critical absenteeism data for teacher {$teacherId}");
                return response()->json($cached);
            }
            
            Log::info("Getting critical absenteeism cases for teacher ID: {$teacherId}");

            // Base query for students exceeding 18 absence limit
            $query = AttendanceAnalyticsCache::where('exceeds_18_absence_limit', true)
                ->where('analysis_date', now()->toDateString())
                ->with(['student' => function($q) {
                    $q->select('id', 'firstName', 'lastName', 'studentId', 'current_status');
                }]);

            // Filter by teacher if specified - use correct table relationships
            if ($teacherId) {
                // Get students assigned to this teacher through sections
                $teacherStudentIds = DB::table('teacher_section_subject as tss')
                    ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
                    ->where('tss.teacher_id', $teacherId)
                    ->where('tss.is_active', true)
                    ->pluck('ss.student_id');
                
                Log::info("Found " . $teacherStudentIds->count() . " students for teacher {$teacherId}");
                
                if ($teacherStudentIds->isNotEmpty()) {
                    $query->whereIn('student_id', $teacherStudentIds);
                } else {
                    // No students found for this teacher, return empty result
                    $query->whereRaw('1 = 0'); // Forces empty result
                }
            }

            $criticalCases = $query->orderBy('total_absences_this_year', 'desc')
                ->limit(50) // Limit to prevent excessive processing
                ->get();
            
            Log::info("Found " . $criticalCases->count() . " critical cases in cache for teacher {$teacherId}");

            // Use cached analytics data instead of generating fresh analytics
            $results = [];
            foreach ($criticalCases as $case) {
                try {
                    // Use cached data from AttendanceAnalyticsCache instead of generating fresh analytics
                    $results[] = [
                        'student_id' => $case->student_id,
                        'student_name' => $case->student ? $case->student->firstName . ' ' . $case->student->lastName : 'Unknown',
                        'student_number' => $case->student ? $case->student->studentId : 'N/A',
                        'total_absences' => $case->total_absences_this_year,
                        'consecutive_absences' => $case->consecutive_absences,
                        'attendance_rate' => $case->attendance_rate,
                        'days_since_last_present' => $case->days_since_last_present,
                        'risk_level' => $case->total_absences_this_year >= 25 ? 'Critical' : 'High',
                        'last_analysis' => $case->analysis_date,
                        'status' => $case->student ? $case->student->current_status : 'unknown'
                    ];
                } catch (\Exception $e) {
                    Log::warning("Failed to process cached data for student {$case->student_id}: " . $e->getMessage());
                    // Continue with other students
                }
            }

            Log::info("Successfully processed " . count($results) . " critical absenteeism cases");

            $response = [
                'success' => true,
                'data' => [
                    'critical_cases' => $results,
                    'total_count' => count($results),
                    'threshold' => 18,
                    'cache_records_found' => $criticalCases->count(),
                    'teacher_id' => $teacherId,
                    'debug_info' => [
                        'teacher_student_count' => isset($teacherStudentIds) ? $teacherStudentIds->count() : 0,
                        'total_cache_records' => AttendanceAnalyticsCache::count(),
                        'today_cache_records' => AttendanceAnalyticsCache::where('analysis_date', now()->toDateString())->count()
                    ]
                ],
                'message' => count($results) > 0 ? 'Critical absenteeism cases retrieved successfully' : 'No critical absenteeism cases found'
            ];

            // Cache the response for 30 minutes
            cache()->put($cacheKey, $response, 1800);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error("Error getting critical absenteeism cases: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical cases',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get attendance patterns and trends
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAttendancePatterns(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|integer|exists:student_details,id',
                'days' => 'integer|min:7|max:90'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $studentId = $request->input('student_id');
            $days = $request->input('days', 30);

            Log::info("Analyzing attendance patterns for student {$studentId} over {$days} days");

            // Get recent analytics cache
            $cache = AttendanceAnalyticsCache::where('student_id', $studentId)
                ->where('analysis_date', now()->toDateString())
                ->first();

            if (!$cache) {
                // Generate fresh analytics if cache doesn't exist
                $cache = AttendanceAnalyticsCache::generateForStudent($studentId);
            }

            // Get detailed pattern analysis
            $patterns = [
                'risk_level' => $cache->risk_level,
                'detected_patterns' => $cache->formatted_patterns,
                'subject_specific' => $cache->subject_specific_data,
                'weekly_trends' => $this->getWeeklyTrends($studentId, $days),
                'tardiness_analysis' => $this->getTardinessAnalysis($studentId, $days),
                'recommendations' => $cache->generateRecommendations()
            ];

            return response()->json([
                'success' => true,
                'data' => $patterns,
                'analysis_period' => "{$days} days",
                'message' => 'Attendance patterns analyzed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error analyzing attendance patterns: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze attendance patterns',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Refresh analytics cache for a student
     * 
     * @param int $studentId
     * @return JsonResponse
     */
    public function refreshStudentAnalytics(int $studentId): JsonResponse
    {
        try {
            Log::info("Refreshing analytics cache for student ID: {$studentId}");

            // Verify student exists
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Force regenerate analytics cache
            $cache = AttendanceAnalyticsCache::generateForStudent($studentId, now());

            // Create notifications if needed
            $this->analyticsService->createAttendanceNotifications($studentId);

            Log::info("Analytics cache refreshed successfully for student: {$student->firstName} {$student->lastName}");

            return response()->json([
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'risk_level' => $cache->risk_level,
                    'exceeds_18_limit' => $cache->exceeds_18_absence_limit,
                    'last_updated' => $cache->last_updated->format('Y-m-d H:i:s')
                ],
                'message' => 'Analytics cache refreshed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error refreshing analytics cache: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh analytics cache',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get urgency legend for UI
     * 
     * @return JsonResponse
     */
    public function getUrgencyLegend(): JsonResponse
    {
        try {
            $legend = $this->analyticsService->getUrgencyLegend();

            return response()->json([
                'success' => true,
                'data' => $legend,
                'message' => 'Urgency legend retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting urgency legend: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve urgency legend',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk refresh analytics for multiple students
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkRefreshAnalytics(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_ids' => 'required|array|max:50',
                'student_ids.*' => 'integer|exists:student_details,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $studentIds = $request->input('student_ids');
            
            Log::info("Bulk refreshing analytics for " . count($studentIds) . " students");

            $results = [];
            $errors = [];

            foreach ($studentIds as $studentId) {
                try {
                    $cache = AttendanceAnalyticsCache::generateForStudent($studentId, now());
                    $this->analyticsService->createAttendanceNotifications($studentId);
                    
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'success',
                        'risk_level' => $cache->risk_level,
                        'exceeds_18_limit' => $cache->exceeds_18_absence_limit
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info("Bulk refresh completed: " . count($results) . " successful, " . count($errors) . " errors");

            return response()->json([
                'success' => true,
                'data' => [
                    'successful' => $results,
                    'errors' => $errors,
                    'total_processed' => count($studentIds),
                    'success_count' => count($results),
                    'error_count' => count($errors)
                ],
                'message' => 'Bulk analytics refresh completed'
            ]);

        } catch (\Exception $e) {
            Log::error("Error in bulk refresh analytics: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk refresh analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get weekly attendance trends
     */
    private function getWeeklyTrends(int $studentId, int $days): array
    {
        $startDate = now()->subDays($days);
        
        $attendance = \App\Models\Attendance::where('student_id', $studentId)
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->get();

        $weeklyData = [];
        $currentWeek = null;
        $weekData = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

        foreach ($attendance as $record) {
            $week = $record->date->format('Y-W');
            
            if ($currentWeek !== $week) {
                if ($currentWeek !== null) {
                    $weeklyData[$currentWeek] = $weekData;
                }
                $currentWeek = $week;
                $weekData = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
            }
            
            $status = $record->status === 'excused' ? 'excused' : $record->status;
            $weekData[$status]++;
        }
        
        if ($currentWeek !== null) {
            $weeklyData[$currentWeek] = $weekData;
        }

        return $weeklyData;
    }

    /**
     * Get detailed tardiness analysis
     */
    private function getTardinessAnalysis(int $studentId, int $days): array
    {
        $startDate = now()->subDays($days);
        
        $tardiness = \App\Models\Attendance::where('student_id', $studentId)
            ->where('status', 'late')
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->get();

        $analysis = [
            'total_tardies' => $tardiness->count(),
            'days_with_tardiness' => $tardiness->pluck('date')->unique()->count(),
            'most_common_day' => null,
            'recent_pattern' => []
        ];

        if ($tardiness->count() > 0) {
            // Find most common day of week for tardiness
            $dayCount = $tardiness->groupBy(function ($item) {
                return $item->date->format('l');
            })->map->count()->sortDesc();
            
            $analysis['most_common_day'] = $dayCount->keys()->first();
            
            // Recent pattern (last 2 weeks)
            $recentTardiness = $tardiness->where('date', '>=', now()->subDays(14));
            $analysis['recent_pattern'] = $recentTardiness->pluck('date')->map(function ($date) {
                return $date->format('Y-m-d');
            })->toArray();
        }

        return $analysis;
    }
}
