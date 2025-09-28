<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceSummaryController extends Controller
{
    /**
     * Get attendance summary for teacher's students - OPTIMIZED VERSION
     */
    public function getTeacherAttendanceSummary(Request $request)
    {
        $startTime = microtime(true);

        try {
            $teacherId = $request->query('teacher_id');
            $period = $request->query('period', 'week');
            $viewType = $request->query('view_type', 'subject');
            $subjectId = $request->query('subject_id');

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID is required'
                ], 400);
            }

            Log::info("Loading attendance summary for teacher {$teacherId}, period: {$period}, viewType: {$viewType}, subjectId: {$subjectId}");

            // Calculate date range based on period
            $endDate = Carbon::now();
            switch ($period) {
                case 'day':
                    $startDate = $endDate->copy()->subDays(7);
                    break;
                case 'week':
                    $startDate = $endDate->copy()->subWeeks(4);
                    break;
                case 'month':
                default:
                    $startDate = $endDate->copy()->subMonths(1);
                    break;
            }

            // OPTIMIZED: Single query to get students with attendance data and proper grade/section info
            $studentsQuery = DB::table('student_details as sd')
                ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                ->join('teacher_section_subject as tss', 'ss.section_id', '=', 'tss.section_id')
                ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
                ->join('curriculum_grade as cg', 'sec.curriculum_grade_id', '=', 'cg.id')
                ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                ->leftJoin('attendance_records as ar', 'sd.id', '=', 'ar.student_id')
                ->leftJoin('attendance_sessions as ases', function($join) use ($startDate, $endDate) {
                    $join->on('ar.attendance_session_id', '=', 'ases.id')
                         ->whereBetween('ases.session_date', [$startDate, $endDate]);
                })
                ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->where('tss.teacher_id', $teacherId)
                ->where('tss.is_active', true)
                ->where('ss.is_active', true)
                ->where('sd.current_status', 'active');

            if ($viewType === 'subject' && $subjectId) {
                $studentsQuery->where('tss.subject_id', $subjectId);
            }

            $students = $studentsQuery->select([
                'sd.id as student_id',
                'sd.firstName as first_name',
                'sd.lastName as last_name',
                'sd.name as full_name',
                'ss.section_id',
                'sec.name as section_name',
                'g.name as grade_name',
                DB::raw('COUNT(CASE WHEN ast.code = \'A\' THEN 1 END) as total_absences'),
                DB::raw('COUNT(CASE WHEN ast.code = \'P\' THEN 1 END) as total_present'),
                DB::raw('COUNT(CASE WHEN ast.code = \'L\' THEN 1 END) as total_late'),
                DB::raw('COUNT(ar.id) as total_records')
            ])
            ->groupBy('sd.id', 'sd.firstName', 'sd.lastName', 'sd.name', 'ss.section_id', 'sec.name', 'g.name')
            ->get();

            Log::info("Found {$students->count()} students for teacher {$teacherId}");

            $totalStudents = $students->count();
            $studentsWithWarning = 0;
            $studentsWithCritical = 0;
            $totalPresent = 0;
            $totalAbsent = 0;

            $studentSummaries = [];

            foreach ($students as $student) {
                $absences = (int)$student->total_absences;
                $present = (int)$student->total_present;
                $late = (int)$student->total_late;
                $totalRecords = (int)$student->total_records;

                $attendanceRate = $totalRecords > 0 ? round(($present / $totalRecords) * 100, 1) : 0;

                // Determine severity based on absences
                $severity = 'normal';
                if ($absences >= 5) {
                    $severity = 'critical';
                    $studentsWithCritical++;
                } elseif ($absences >= 3) {
                    $severity = 'warning';
                    $studentsWithWarning++;
                }

                $totalPresent += $present;
                $totalAbsent += $absences;

                $studentSummaries[] = [
                    'student_id' => $student->student_id,
                    'name' => $student->full_name ?: ($student->first_name . ' ' . $student->last_name),
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'section_id' => $student->section_id,
                    'section_name' => $student->section_name,
                    'grade_name' => $student->grade_name,
                    'total_absences' => $absences,
                    'total_present' => $present,
                    'total_late' => $late,
                    'attendance_rate' => $attendanceRate,
                    'severity' => $severity,
                    'recent_absences' => $absences
                ];
            }

            // Calculate overall average attendance
            $totalRecords = $totalPresent + $totalAbsent;
            $averageAttendance = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_students' => $totalStudents,
                    'average_attendance' => $averageAttendance,
                    'students_with_warning' => $studentsWithWarning,
                    'students_with_critical' => $studentsWithCritical,
                    'students' => $studentSummaries,
                    'period' => $period,
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ]
                ],
                'execution_time_ms' => $executionTime,
                'message' => 'Attendance summary retrieved successfully'
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Error in getTeacherAttendanceSummary: ' . $e->getMessage(), [
                'teacher_id' => $teacherId ?? null,
                'execution_time_ms' => $executionTime,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance summary',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'execution_time_ms' => $executionTime
            ], 500);
        }
    }

    /**
     * Get attendance trends for charts - OPTIMIZED VERSION
     */
    public function getTeacherAttendanceTrends(Request $request)
    {
        $startTime = microtime(true);

        try {
            $teacherId = $request->query('teacher_id');
            $period = $request->query('period', 'week');
            $viewType = $request->query('view_type', 'subject');
            $subjectId = $request->query('subject_id');

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID is required'
                ], 400);
            }

            // Calculate date range and labels
            $endDate = Carbon::now();
            $labels = [];
            $periods = [];

            switch ($period) {
                case 'day':
                    for ($i = 6; $i >= 0; $i--) {
                        $date = $endDate->copy()->subDays($i);
                        $labels[] = $date->format('M j');
                        $periods[] = $date->format('Y-m-d');
                    }
                    break;
                case 'week':
                    for ($i = 3; $i >= 0; $i--) {
                        $weekEnd = $endDate->copy()->subWeeks($i);
                        $weekStart = $weekEnd->copy()->subDays(6);
                        $labels[] = $weekStart->format('M j') . ' - ' . $weekEnd->format('M j');
                        $periods[] = [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')];
                    }
                    break;
                case 'month':
                    for ($i = 5; $i >= 0; $i--) {
                        $date = $endDate->copy()->subMonths($i);
                        $labels[] = $date->format('M Y');
                        $periods[] = [
                            $date->startOfMonth()->format('Y-m-d'),
                            $date->endOfMonth()->format('Y-m-d')
                        ];
                    }
                    break;
            }

            // Get attendance data for each period
            $presentData = [];
            $absentData = [];
            $lateData = [];

            foreach ($periods as $periodRange) {
                $startDate = is_array($periodRange) ? $periodRange[0] : $periodRange;
                $endDateRange = is_array($periodRange) ? $periodRange[1] : $periodRange;

                $attendanceQuery = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                    ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
                    ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                    ->join('teacher_section_subject as tss', 'ss.section_id', '=', 'tss.section_id')
                    ->where('tss.teacher_id', $teacherId)
                    ->where('tss.is_active', true)
                    ->where('ss.is_active', true)
                    ->whereBetween('ases.session_date', [$startDate, $endDateRange]);

                if ($viewType === 'subject' && $subjectId) {
                    $attendanceQuery->where('tss.subject_id', $subjectId);
                }

                $counts = $attendanceQuery->select([
                    DB::raw('COUNT(CASE WHEN ast.code = \'P\' THEN 1 END) as present_count'),
                    DB::raw('COUNT(CASE WHEN ast.code = \'A\' THEN 1 END) as absent_count'),
                    DB::raw('COUNT(CASE WHEN ast.code = \'L\' THEN 1 END) as late_count')
                ])->first();

                $presentData[] = (int)$counts->present_count;
                $absentData[] = (int)$counts->absent_count;
                $lateData[] = (int)$counts->late_count;
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Present',
                            'data' => $presentData,
                            'backgroundColor' => 'rgba(16, 185, 129, 0.9)',
                            'borderColor' => '#10b981'
                        ],
                        [
                            'label' => 'Absent',
                            'data' => $absentData,
                            'backgroundColor' => 'rgba(239, 68, 68, 0.9)',
                            'borderColor' => '#ef4444'
                        ],
                        [
                            'label' => 'Late',
                            'data' => $lateData,
                            'backgroundColor' => 'rgba(245, 158, 11, 0.9)',
                            'borderColor' => '#f59e0b'
                        ]
                    ]
                ],
                'execution_time_ms' => $executionTime,
                'message' => 'Attendance trends retrieved successfully'
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Error in getTeacherAttendanceTrends: ' . $e->getMessage(), [
                'teacher_id' => $teacherId ?? null,
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance trends',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'execution_time_ms' => $executionTime
            ], 500);
        }
    }

    /**
     * Get attendance records for student calendar display
     */
    public function getStudentAttendanceRecords(Request $request)
    {
        $startTime = microtime(true);
        
        try {
            $studentIds = $request->query('student_ids');
            $subjectId = $request->query('subject_id');
            $month = $request->query('month');
            $year = $request->query('year');

            if (!$studentIds) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student IDs are required'
                ], 400);
            }

            // Convert student_ids to array
            $studentIdsArray = is_string($studentIds) ? explode(',', $studentIds) : [$studentIds];
            
            // Calculate date range for the month
            $startDate = Carbon::create($year, $month + 1, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month + 1, 1)->endOfMonth();

            Log::info("Getting attendance records for students: " . implode(',', $studentIdsArray) . " for month {$month}/{$year}");

            // Get attendance records from attendance_records table
            $recordsQuery = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereIn('ar.student_id', $studentIdsArray)
                ->whereBetween('ases.session_date', [$startDate, $endDate]);

            if ($subjectId) {
                $recordsQuery->where('ases.subject_id', $subjectId);
            }

            $records = $recordsQuery->select([
                'ar.student_id',
                'ases.session_date as date',
                'ast.code',
                'ast.name as status_name',
                'ases.subject_id'
            ])->get();

            // Transform records to match expected format
            $transformedRecords = $records->map(function($record) {
                $status = 'PRESENT';
                if ($record->code === 'A') $status = 'ABSENT';
                elseif ($record->code === 'L') $status = 'LATE';
                elseif ($record->code === 'E') $status = 'EXCUSED';

                return [
                    'studentId' => $record->student_id,
                    'date' => $record->date,
                    'status' => $status,
                    'subjectId' => $record->subject_id
                ];
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info("Found " . $transformedRecords->count() . " attendance records");

            return response()->json([
                'success' => true,
                'records' => $transformedRecords,
                'count' => $transformedRecords->count(),
                'execution_time_ms' => $executionTime,
                'message' => 'Attendance records retrieved successfully'
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('Error in getStudentAttendanceRecords: ' . $e->getMessage(), [
                'student_ids' => $studentIds ?? null,
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'execution_time_ms' => $executionTime
            ], 500);
        }
    }
}
