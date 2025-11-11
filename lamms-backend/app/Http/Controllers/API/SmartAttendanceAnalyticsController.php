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
     * Get real weekly attendance data for a student (public API endpoint)
     * 
     * @param int $studentId
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudentWeeklyAttendance(int $studentId, Request $request): JsonResponse
    {
        try {
            $month = $request->query('month'); // Format: YYYY-MM
            $year = $request->query('year');
            $subjectId = $request->query('subject_id'); // Filter by subject
            $teacherId = $request->query('teacher_id'); // Filter by teacher (IMPORTANT!)
            
            Log::info("Fetching real weekly attendance for student ID: {$studentId}", [
                'month' => $month,
                'year' => $year,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId
            ]);

            // Verify student exists
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Calculate date range based on month/year or default to last 4 weeks
            if ($month && $year) {
                $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
            } else {
                $endDate = now();
                $startDate = now()->subDays(28);
            }

            // Get weekly attendance data for the specified period (filtered by teacher!)
            $weeklyAttendance = $this->calculateRealWeeklyAttendanceForPeriod($studentId, $startDate, $endDate, $subjectId, $teacherId);

            return response()->json([
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'weekly_attendance' => $weeklyAttendance,
                    'weeks_analyzed' => count($weeklyAttendance)
                ],
                'message' => 'Weekly attendance data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching weekly attendance: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve weekly attendance',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate real weekly attendance for a specific date period
     */
    private function calculateRealWeeklyAttendanceForPeriod(int $studentId, $startDate, $endDate, $subjectId = null, $teacherId = null): array
    {
        
        Log::info("Querying attendance records for student {$studentId} from {$startDate->toDateString()} to {$endDate->toDateString()}");

        // Get REAL attendance records from database (with subject info and optional filter)
        $query = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ase', 'ar.attendance_session_id', '=', 'ase.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->leftJoin('subjects as subj', 'ase.subject_id', '=', 'subj.id')
            ->where('ar.student_id', $studentId)
            ->where('ase.session_date', '>=', $startDate->toDateString())
            ->where('ase.session_date', '<=', $endDate->toDateString());
        
        // Apply subject filter if provided
        if ($subjectId) {
            $query->where('ase.subject_id', $subjectId);
            Log::info("Filtering attendance by subject ID: {$subjectId}");
        }
        
        // Apply teacher filter if provided (CRITICAL - only show THIS teacher's sessions!)
        if ($teacherId) {
            $query->where('ase.teacher_id', $teacherId);
            Log::info("Filtering attendance by teacher ID: {$teacherId}");
        }
        
        $attendanceRecords = $query->select(
                'ase.session_date',
                'ast.code as status_code',
                'ast.name as status_name',
                'ase.subject_id',
                'subj.name as subject_name'
            )
            ->orderBy('ase.session_date')
            ->get();

        Log::info("Found {$attendanceRecords->count()} attendance records");

        // Group by week and count statuses
        $weeklyData = [];
        $weekRanges = $this->generateWeekRanges($startDate, $endDate);

        foreach ($weekRanges as $weekKey => $weekInfo) {
            $weekRecords = $attendanceRecords->filter(function ($record) use ($weekInfo) {
                $recordDate = \Carbon\Carbon::parse($record->session_date);
                return $recordDate->between($weekInfo['start'], $weekInfo['end']);
            });

            $present = 0;
            $absent = 0;
            $late = 0;
            $excused = 0;
            $subjectBreakdown = [];
            $uniqueDays = [];
            $dailyStatuses = []; // Track worst status per day

            foreach ($weekRecords as $record) {
                // Skip weekends (Saturday = 6, Sunday = 0)
                $recordDate = \Carbon\Carbon::parse($record->session_date);
                $dayOfWeek = $recordDate->dayOfWeek;
                if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                    continue; // Skip weekends
                }
                
                // Track unique days
                $day = $record->session_date;
                if (!isset($uniqueDays[$day])) {
                    $uniqueDays[$day] = [];
                }
                
                // Track subjects with their IDs
                $subject = $record->subject_name ?? 'Unknown Subject';
                $subjectKey = $record->subject_id . ':' . $subject; // Store with subject_id
                
                if (!isset($subjectBreakdown[$subjectKey])) {
                    $subjectBreakdown[$subjectKey] = [
                        'subject_id' => $record->subject_id,
                        'subject_name' => $subject,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'excused' => 0
                    ];
                }

                // Track status per subject for breakdown
                switch ($record->status_code) {
                    case 'P':
                        $subjectBreakdown[$subjectKey]['present']++;
                        break;
                    case 'A':
                        $subjectBreakdown[$subjectKey]['absent']++;
                        break;
                    case 'L':
                        $subjectBreakdown[$subjectKey]['late']++;
                        break;
                    case 'E':
                        $subjectBreakdown[$subjectKey]['excused']++;
                        break;
                }
                
                // Track worst status per day (priority: Absent > Excused > Late > Present)
                // If student is absent in ANY session that day, mark whole day as absent
                if (!isset($dailyStatuses[$day])) {
                    $dailyStatuses[$day] = $record->status_code;
                } else {
                    // Priority order: A (worst) > E > L > P (best)
                    $currentPriority = $this->getStatusPriority($dailyStatuses[$day]);
                    $newPriority = $this->getStatusPriority($record->status_code);
                    
                    if ($newPriority > $currentPriority) {
                        $dailyStatuses[$day] = $record->status_code;
                    }
                }
                
                $uniqueDays[$day][] = $subject;
            }

            // Count daily statuses (only 1 status per day based on worst status)
            foreach ($dailyStatuses as $day => $status) {
                switch ($status) {
                    case 'P':
                        $present++;
                        break;
                    case 'A':
                        $absent++;
                        break;
                    case 'L':
                        $late++;
                        break;
                    case 'E':
                        $excused++;
                        break;
                }
            }

            // Calculate percentage based on DAYS, not individual records
            // Count late as present for percentage (they attended, just late)
            $totalAttended = $present + $late;
            $totalDays = count($uniqueDays);
            $percentage = $totalDays > 0 ? round(($totalAttended / $totalDays) * 100) : 0;
            $totalSubjects = count($subjectBreakdown);

            $weeklyData[] = [
                'week' => $weekInfo['label'],
                'start_date' => $weekInfo['start']->toDateString(),
                'end_date' => $weekInfo['end']->toDateString(),
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'total_records' => $present + $absent + $late + $excused,
                'total_days' => $totalDays,
                'total_subjects' => $totalSubjects,
                'subject_breakdown' => $subjectBreakdown,
                'percentage' => $percentage
            ];
        }

        return $weeklyData;
    }

    /**
     * Generate week ranges for the given period (WEEKDAYS ONLY - Monday to Friday)
     */
    private function generateWeekRanges($startDate, $endDate): array
    {
        $weeks = [];
        
        // Find the first Monday on or before startDate
        $firstMonday = $startDate->copy()->startOfWeek();
        
        // Find the last Friday (not Sunday) on or after endDate
        $lastFriday = $endDate->copy();
        while ($lastFriday->dayOfWeek !== 5) { // 5 = Friday
            $lastFriday->addDay();
        }
        
        $currentWeekStart = $firstMonday->copy();

        while ($currentWeekStart->lte($lastFriday)) {
            $weekStart = $currentWeekStart->copy(); // Monday
            $weekEnd = $currentWeekStart->copy()->addDays(4); // Friday (Monday + 4 days)

            // Only include weeks that have at least one weekday in the target period
            if ($weekEnd->gte($startDate) && $weekStart->lte($endDate)) {
                $weekKey = $weekStart->format('Y-W');
                $weeks[$weekKey] = [
                    'start' => $weekStart,
                    'end' => $weekEnd,
                    'label' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j') // Monday - Friday
                ];
            }

            $currentWeekStart->addWeek();
        }

        return $weeks;
    }

    /**
     * Get status priority for determining worst status per day
     * Higher number = worse status (Absent is worst, Present is best)
     */
    private function getStatusPriority(string $statusCode): int
    {
        switch ($statusCode) {
            case 'A': // Absent - worst
                return 4;
            case 'E': // Excused
                return 3;
            case 'L': // Late
                return 2;
            case 'P': // Present - best
                return 1;
            default:
                return 0;
        }
    }

    /**
     * Get weekly attendance trends (for internal use)
     */
    private function getWeeklyTrends(int $studentId, int $days): array
    {
        $endDate = now();
        $startDate = now()->subDays($days);
        return $this->calculateRealWeeklyAttendanceForPeriod($studentId, $startDate, $endDate);
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
