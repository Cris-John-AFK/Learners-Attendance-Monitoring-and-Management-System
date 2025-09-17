<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminAttendanceAnalyticsController extends Controller
{
    /**
     * Get attendance analytics aggregated by grades for admin dashboard
     */
    public function getAttendanceAnalytics(Request $request)
    {
        try {
            $dateRange = $request->query('date_range', 'current_year');
            $gradeId = $request->query('grade_id');

            // Get date range
            $dates = $this->getDateRange($dateRange);
            $startDate = $dates['start'];
            $endDate = $dates['end'];

            // Get grades
            $gradesQuery = DB::table('grades')->where('is_active', true);
            if ($gradeId) {
                $gradesQuery->where('id', $gradeId);
            }
            $grades = $gradesQuery->get();
            
            $analyticsData = [];
            $totalPresent = 0;
            $totalAbsent = 0;
            $totalLate = 0;
            $totalExcused = 0;
            $totalRecords = 0;

            foreach ($grades as $grade) {
                // Get students in this grade through sections
                $studentIds = DB::table('student_section as ss')
                    ->join('sections as s', 'ss.section_id', '=', 's.id')
                    ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                    ->where('cg.grade_id', $grade->id)
                    ->where('ss.is_active', true)
                    ->pluck('ss.student_id');


                if ($studentIds->isEmpty()) {
                    $analyticsData[] = [
                        'grade_id' => $grade->id,
                        'grade_name' => $grade->name,
                        'grade_code' => $grade->code ?? $grade->name,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'excused' => 0,
                        'total_records' => 0,
                        'attendance_rate' => 0
                    ];
                    continue;
                }

                // Check if we have attendance_records table (production) or attendances table (basic)
                $hasAttendanceRecords = true;
                try {
                    DB::table('attendance_records')->limit(1)->get();
                } catch (\Exception $e) {
                    $hasAttendanceRecords = false;
                }

                if ($hasAttendanceRecords) {
                    // Use production attendance system
                    $attendanceData = $this->getProductionAttendanceData($studentIds, $startDate, $endDate);
                } else {
                    // Use basic attendance system
                    $attendanceData = $this->getBasicAttendanceData($studentIds, $startDate, $endDate);
                }

                $present = $attendanceData['present'];
                $absent = $attendanceData['absent'];
                $late = $attendanceData['late'];
                $excused = $attendanceData['excused'];
                $records = $present + $absent + $late + $excused;
                $rate = $records > 0 ? round(($present + $late) / $records * 100, 1) : 0;

                $analyticsData[] = [
                    'grade_id' => $grade->id,
                    'grade_name' => $grade->name,
                    'grade_code' => $grade->code ?? $grade->name,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $excused,
                    'total_records' => $records,
                    'attendance_rate' => $rate,
                    'student_count' => $studentIds->count()
                ];

                $totalPresent += $present;
                $totalAbsent += $absent;
                $totalLate += $late;
                $totalExcused += $excused;
                $totalRecords += $records;
            }

            $overallRate = $totalRecords > 0 ? round(($totalPresent + $totalLate) / $totalRecords * 100, 1) : 0;
            $totalStudents = array_sum(array_column($analyticsData, 'student_count'));

            return response()->json([
                'success' => true,
                'data' => [
                    'date_range' => $dateRange,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'grades' => $analyticsData,
                    'summary' => [
                        'total_present' => $totalPresent,
                        'total_absent' => $totalAbsent,
                        'total_late' => $totalLate,
                        'total_excused' => $totalExcused,
                        'total_records' => $totalRecords,
                        'total_students' => $totalStudents,
                        'overall_attendance_rate' => $overallRate
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance analytics: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get date range based on period
     */
    private function getDateRange($dateRange)
    {
        $endDate = Carbon::now();
        
        switch ($dateRange) {
            case 'last_7_days':
                $startDate = $endDate->copy()->subDays(7);
                break;
            case 'last_30_days':
                $startDate = $endDate->copy()->subDays(30);
                break;
            case 'current_year':
            default:
                // Current school year (June to May)
                $currentYear = Carbon::now()->year;
                $currentMonth = Carbon::now()->month;
                
                if ($currentMonth >= 6) {
                    // June to December - current school year
                    $startDate = Carbon::create($currentYear, 6, 1);
                    $endDate = Carbon::create($currentYear + 1, 5, 31);
                } else {
                    // January to May - previous school year started
                    $startDate = Carbon::create($currentYear - 1, 6, 1);
                    $endDate = Carbon::create($currentYear, 5, 31);
                }
                break;
        }

        return [
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString()
        ];
    }

    /**
     * Get attendance count by status name
     */
    private function getAttendanceCountByStatus($studentIds, $startDate, $endDate, $statusName)
    {
        return DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->whereIn('ar.student_id', $studentIds)
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->where('ast.name', $statusName)
            ->count();
    }

    /**
     * Get attendance count by status ID (fallback)
     */
    private function getAttendanceCountByStatusId($studentIds, $startDate, $endDate, $statusId)
    {
        return DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->whereIn('ar.student_id', $studentIds)
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->where('ar.attendance_status_id', $statusId)
            ->count();
    }

    /**
     * Get attendance trends over time for admin dashboard
     */
    public function getAttendanceTrends(Request $request)
    {
        try {
            $dateRange = $request->query('date_range', 'last_30_days');
            $gradeId = $request->query('grade_id');

            // Check if attendance_statuses table exists
            $hasStatusTable = true;
            try {
                DB::table('attendance_statuses')->limit(1)->get();
            } catch (\Exception $e) {
                $hasStatusTable = false;
            }

            $trendsData = [];

            switch ($dateRange) {
                case 'last_7_days':
                    $trendsData = $this->getDailyTrends($gradeId, $hasStatusTable, 7);
                    break;
                case 'last_30_days':
                    $trendsData = $this->getDailyTrends($gradeId, $hasStatusTable, 30);
                    break;
                case 'current_year':
                    $trendsData = $this->getMonthlyTrends($gradeId, $hasStatusTable);
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $trendsData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance trends: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily trends for specified number of days
     */
    private function getDailyTrends($gradeId, $hasStatusTable, $days = 30)
    {
        $dateLabels = [];
        $presentData = [];
        $absentData = [];
        $lateData = [];
        $excusedData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $dateLabels[] = Carbon::parse($date)->format('M j');

            // Get students for the grade (if specified)
            $studentsQuery = DB::table('student_details as sd')
                ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                ->join('sections as s', 'ss.section_id', '=', 's.id')
                ->where('ss.is_active', true)
                ->where('sd.isActive', true);

            if ($gradeId) {
                $studentsQuery->where('s.grade_id', $gradeId);
            }

            $studentIds = $studentsQuery->pluck('sd.id');

            if ($studentIds->isEmpty()) {
                $presentData[] = 0;
                $absentData[] = 0;
                $lateData[] = 0;
                $excusedData[] = 0;
                continue;
            }

            if ($hasStatusTable) {
                $presentCount = $this->getAttendanceCountByStatusForDate($studentIds, $date, 'Present');
                $absentCount = $this->getAttendanceCountByStatusForDate($studentIds, $date, 'Absent');
                $lateCount = $this->getAttendanceCountByStatusForDate($studentIds, $date, 'Late');
                $excusedCount = $this->getAttendanceCountByStatusForDate($studentIds, $date, 'Excused');
            } else {
                $presentCount = $this->getAttendanceCountByStatusIdForDate($studentIds, $date, 1);
                $absentCount = $this->getAttendanceCountByStatusIdForDate($studentIds, $date, 2);
                $lateCount = $this->getAttendanceCountByStatusIdForDate($studentIds, $date, 3);
                $excusedCount = $this->getAttendanceCountByStatusIdForDate($studentIds, $date, 4);
            }

            $presentData[] = $presentCount;
            $absentData[] = $absentCount;
            $lateData[] = $lateCount;
            $excusedData[] = $excusedCount;
        }

        return [
            'labels' => $dateLabels,
            'datasets' => [
                [
                    'label' => 'Present',
                    'backgroundColor' => '#10b981',
                    'data' => $presentData
                ],
                [
                    'label' => 'Absent',
                    'backgroundColor' => '#ef4444',
                    'data' => $absentData
                ],
                [
                    'label' => 'Late',
                    'backgroundColor' => '#f59e0b',
                    'data' => $lateData
                ],
                [
                    'label' => 'Excused',
                    'backgroundColor' => '#6b7280',
                    'data' => $excusedData
                ]
            ]
        ];
    }

    /**
     * Get monthly trends for current school year
     */
    private function getMonthlyTrends($gradeId, $hasStatusTable)
    {
        // Get school year months (June to current month or May)
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;
        
        $months = [];
        if ($currentMonth >= 6) {
            // June to current month
            for ($month = 6; $month <= $currentMonth; $month++) {
                $months[] = Carbon::create($currentYear, $month, 1);
            }
        } else {
            // June to December of previous year, then January to current month
            for ($month = 6; $month <= 12; $month++) {
                $months[] = Carbon::create($currentYear - 1, $month, 1);
            }
            for ($month = 1; $month <= $currentMonth; $month++) {
                $months[] = Carbon::create($currentYear, $month, 1);
            }
        }

        $dateLabels = [];
        $presentData = [];
        $absentData = [];
        $lateData = [];
        $excusedData = [];

        foreach ($months as $monthStart) {
            $monthEnd = $monthStart->copy()->endOfMonth();
            $dateLabels[] = $monthStart->format('M Y');

            // Get students for the grade (if specified)
            $studentsQuery = DB::table('student_details as sd')
                ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                ->join('sections as s', 'ss.section_id', '=', 's.id')
                ->where('ss.is_active', true)
                ->where('sd.isActive', true);

            if ($gradeId) {
                $studentsQuery->where('s.grade_id', $gradeId);
            }

            $studentIds = $studentsQuery->pluck('sd.id');

            if ($studentIds->isEmpty()) {
                $presentData[] = 0;
                $absentData[] = 0;
                $lateData[] = 0;
                $excusedData[] = 0;
                continue;
            }

            if ($hasStatusTable) {
                $presentCount = $this->getAttendanceCountByStatus($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 'Present');
                $absentCount = $this->getAttendanceCountByStatus($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 'Absent');
                $lateCount = $this->getAttendanceCountByStatus($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 'Late');
                $excusedCount = $this->getAttendanceCountByStatus($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 'Excused');
            } else {
                $presentCount = $this->getAttendanceCountByStatusId($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 1);
                $absentCount = $this->getAttendanceCountByStatusId($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 2);
                $lateCount = $this->getAttendanceCountByStatusId($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 3);
                $excusedCount = $this->getAttendanceCountByStatusId($studentIds, $monthStart->toDateString(), $monthEnd->toDateString(), 4);
            }

            $presentData[] = $presentCount;
            $absentData[] = $absentCount;
            $lateData[] = $lateCount;
            $excusedData[] = $excusedCount;
        }

        return [
            'labels' => $dateLabels,
            'datasets' => [
                [
                    'label' => 'Present',
                    'backgroundColor' => '#10b981',
                    'data' => $presentData
                ],
                [
                    'label' => 'Absent',
                    'backgroundColor' => '#ef4444',
                    'data' => $absentData
                ],
                [
                    'label' => 'Late',
                    'backgroundColor' => '#f59e0b',
                    'data' => $lateData
                ],
                [
                    'label' => 'Excused',
                    'backgroundColor' => '#6b7280',
                    'data' => $excusedData
                ]
            ]
        ];
    }

    /**
     * Get attendance count by status for specific date
     */
    private function getAttendanceCountByStatusForDate($studentIds, $date, $statusName)
    {
        return DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->whereIn('ar.student_id', $studentIds)
            ->where('ases.session_date', $date)
            ->where('ast.name', $statusName)
            ->count();
    }

    /**
     * Get attendance count by status ID for specific date
     */
    private function getAttendanceCountByStatusIdForDate($studentIds, $date, $statusId)
    {
        return DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->whereIn('ar.student_id', $studentIds)
            ->where('ases.session_date', $date)
            ->where('ar.attendance_status_id', $statusId)
            ->count();
    }

    /**
     * Get attendance data from production attendance system
     */
    private function getProductionAttendanceData($studentIds, $startDate, $endDate)
    {
        // Check if attendance_statuses table exists
        $hasStatusTable = true;
        try {
            DB::table('attendance_statuses')->limit(1)->get();
        } catch (\Exception $e) {
            $hasStatusTable = false;
        }

        if ($hasStatusTable) {
            // Use status names
            $present = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ast.name', 'Present')
                ->count();

            $absent = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ast.name', 'Absent')
                ->count();

            $late = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ast.name', 'Late')
                ->count();

            $excused = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ast.name', 'Excused')
                ->count();
        } else {
            // Use status IDs (1=Present, 2=Absent, 3=Late, 4=Excused)
            $present = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ar.attendance_status_id', 1)
                ->count();

            $absent = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ar.attendance_status_id', 2)
                ->count();

            $late = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ar.attendance_status_id', 3)
                ->count();

            $excused = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->whereIn('ar.student_id', $studentIds)
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                ->where('ar.attendance_status_id', 4)
                ->count();
        }

        return [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused
        ];
    }

    /**
     * Get attendance data from basic attendance system
     */
    private function getBasicAttendanceData($studentIds, $startDate, $endDate)
    {
        $present = DB::table('attendances')
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'present')
            ->count();

        $absent = DB::table('attendances')
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'absent')
            ->count();

        $late = DB::table('attendances')
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'late')
            ->count();

        $excused = DB::table('attendances')
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'excused')
            ->count();

        return [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused
        ];
    }
}
