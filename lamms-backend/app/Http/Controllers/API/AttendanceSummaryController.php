<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceSummaryController extends Controller
{
    /**
     * Get attendance summary for teacher's students
     */
    public function getTeacherAttendanceSummary(Request $request)
    {
        try {
            $teacherId = $request->query('teacher_id');
            $period = $request->query('period', 'month'); // day, week, month
            $viewType = $request->query('view_type', 'subject'); // subject, all_students
            $subjectId = $request->query('subject_id');

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
                    $startDate = $endDate->copy()->subMonths(6);
                    break;
            }

            // Get students for this teacher - check both teacher assignments and actual attendance sessions
            $studentsQuery = DB::table('student_details as sd')
                ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                ->join('teacher_section_subject as tss', 'ss.section_id', '=', 'tss.section_id')
                ->where('tss.teacher_id', $teacherId)
                ->where('ss.is_active', true)
                ->where('sd.isActive', true);

            if ($viewType === 'subject' && $subjectId) {
                $studentsQuery->where('tss.subject_id', $subjectId);
            }

            $students = $studentsQuery->select([
                'sd.id as student_id',
                'sd.firstName as first_name',
                'sd.lastName as last_name',
                'ss.section_id'
            ])->distinct()->get();

            // Also get students who have attendance records for this teacher (fallback)
            if ($students->isEmpty()) {
                $studentsFromAttendance = DB::table('student_details as sd')
                    ->join('attendance_records as ar', 'sd.id', '=', 'ar.student_id')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->where('ases.teacher_id', $teacherId)
                    ->select([
                        'sd.id as student_id',
                        'sd.firstName as first_name',
                        'sd.lastName as last_name',
                        'ases.section_id'
                    ])
                    ->distinct()
                    ->get();
                
                if (!$studentsFromAttendance->isEmpty()) {
                    $students = $studentsFromAttendance;
                }
            }

            $totalStudents = $students->count();
            $studentsWithWarning = 0;
            $studentsWithCritical = 0;

            $studentSummaries = [];

            // Check if attendance_statuses table exists
            $statusTableExists = DB::select("SELECT to_regclass('attendance_statuses') as exists");
            $hasStatusTable = $statusTableExists[0]->exists !== null;

            foreach ($students as $student) {
                $totalAbsences = 0;
                $recentAbsenceCount = 0;

                if ($hasStatusTable) {
                    // Use proper joins with attendance_statuses table
                    $absenceQuery = DB::table('attendance_records as ar')
                        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                        ->where('ar.student_id', $student->student_id)
                        ->where('ases.teacher_id', $teacherId)
                        ->where('ast.name', 'Absent');

                    if ($viewType === 'subject' && $subjectId) {
                        $absenceQuery->where('ases.subject_id', $subjectId);
                    }

                    $totalAbsences = $absenceQuery->count();

                    // Count recent absences (past week)
                    $recentAbsenceQuery = DB::table('attendance_records as ar')
                        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                        ->where('ar.student_id', $student->student_id)
                        ->where('ases.teacher_id', $teacherId)
                        ->where('ast.name', 'Absent')
                        ->where('ases.session_date', '>=', Carbon::now()->subWeek());

                    if ($viewType === 'subject' && $subjectId) {
                        $recentAbsenceQuery->where('ases.subject_id', $subjectId);
                    }

                    $recentAbsenceCount = $recentAbsenceQuery->count();
                } else {
                    // Fallback: assume attendance_status_id 2 is absent (based on sample data)
                    $absenceQuery = DB::table('attendance_records as ar')
                        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                        ->where('ar.student_id', $student->student_id)
                        ->where('ases.teacher_id', $teacherId)
                        ->where('ar.attendance_status_id', 2); // Assuming 2 = absent

                    if ($viewType === 'subject' && $subjectId) {
                        $absenceQuery->where('ases.subject_id', $subjectId);
                    }

                    $totalAbsences = $absenceQuery->count();

                    // Recent absences
                    $recentAbsenceQuery = DB::table('attendance_records as ar')
                        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                        ->where('ar.student_id', $student->student_id)
                        ->where('ases.teacher_id', $teacherId)
                        ->where('ar.attendance_status_id', 2)
                        ->where('ases.session_date', '>=', Carbon::now()->subWeek());

                    if ($viewType === 'subject' && $subjectId) {
                        $recentAbsenceQuery->where('ases.subject_id', $subjectId);
                    }

                    $recentAbsenceCount = $recentAbsenceQuery->count();
                }

                // Determine severity
                if ($recentAbsenceCount >= 5) {
                    $studentsWithCritical++;
                } elseif ($recentAbsenceCount >= 3) {
                    $studentsWithWarning++;
                }

                $studentSummaries[] = [
                    'student_id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'total_absences' => $totalAbsences,
                    'recent_absences' => $recentAbsenceCount
                ];
            }

            // Calculate average attendance
            $presentCount = 0;
            if ($hasStatusTable) {
                $presentQuery = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                    ->whereIn('ar.student_id', $students->pluck('student_id'))
                    ->where('ases.teacher_id', $teacherId)
                    ->where('ast.name', 'Present')
                    ->whereBetween('ases.session_date', [$startDate, $endDate]);

                if ($viewType === 'subject' && $subjectId) {
                    $presentQuery->where('ases.subject_id', $subjectId);
                }

                $presentCount = $presentQuery->count();
            } else {
                // Fallback: assume attendance_status_id 1 is present
                $presentQuery = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->whereIn('ar.student_id', $students->pluck('student_id'))
                    ->where('ases.teacher_id', $teacherId)
                    ->where('ar.attendance_status_id', 1)
                    ->whereBetween('ases.session_date', [$startDate, $endDate]);

                if ($viewType === 'subject' && $subjectId) {
                    $presentQuery->where('ases.subject_id', $subjectId);
                }

                $presentCount = $presentQuery->count();
            }

            // Calculate total attendance records (present + absent + late + excused)
            $totalAttendanceRecords = 0;
            if ($hasStatusTable) {
                $totalRecordsQuery = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->whereIn('ar.student_id', $students->pluck('student_id'))
                    ->where('ases.teacher_id', $teacherId)
                    ->whereBetween('ases.session_date', [$startDate, $endDate]);

                if ($viewType === 'subject' && $subjectId) {
                    $totalRecordsQuery->where('ases.subject_id', $subjectId);
                }

                $totalAttendanceRecords = $totalRecordsQuery->count();
            } else {
                $totalRecordsQuery = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->whereIn('ar.student_id', $students->pluck('student_id'))
                    ->where('ases.teacher_id', $teacherId)
                    ->whereBetween('ases.session_date', [$startDate, $endDate]);

                if ($viewType === 'subject' && $subjectId) {
                    $totalRecordsQuery->where('ases.subject_id', $subjectId);
                }

                $totalAttendanceRecords = $totalRecordsQuery->count();
            }

            // Calculate average attendance based on actual records
            $averageAttendance = $totalAttendanceRecords > 0 ? 
                round(($presentCount / $totalAttendanceRecords) * 100) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_students' => $totalStudents,
                    'average_attendance' => $averageAttendance,
                    'students_with_warning' => $studentsWithWarning,
                    'students_with_critical' => $studentsWithCritical,
                    'students' => $studentSummaries
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance trends for teacher's students
     */
    public function getTeacherAttendanceTrends(Request $request)
    {
        try {
            $teacherId = $request->query('teacher_id');
            $period = $request->query('period', 'week'); // day, week, month
            $viewType = $request->query('view_type', 'subject'); // subject, all_students
            $subjectId = $request->query('subject_id');

            // Check if attendance_statuses table exists
            $statusTableExists = DB::select("SELECT to_regclass('attendance_statuses') as exists");
            $hasStatusTable = $statusTableExists[0]->exists !== null;

            $trendsData = [];

            switch ($period) {
                case 'day':
                    $trendsData = $this->getDailyTrends($teacherId, $subjectId, $viewType, $hasStatusTable);
                    break;
                case 'week':
                    $trendsData = $this->getWeeklyTrends($teacherId, $subjectId, $viewType, $hasStatusTable);
                    break;
                case 'month':
                    $trendsData = $this->getMonthlyTrends($teacherId, $subjectId, $viewType, $hasStatusTable);
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

    private function getDailyTrends($teacherId, $subjectId, $viewType, $hasStatusTable)
    {
        // Get last 7 days
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $days[] = $date;
        }

        $presentData = [];
        $absentData = [];
        $lateData = [];
        $excusedData = [];

        foreach ($days as $date) {
            if ($hasStatusTable) {
                $presentCount = $this->getAttendanceCountByStatus($teacherId, $subjectId, $viewType, $date, 'present');
                $absentCount = $this->getAttendanceCountByStatus($teacherId, $subjectId, $viewType, $date, 'absent');
                $lateCount = $this->getAttendanceCountByStatus($teacherId, $subjectId, $viewType, $date, 'late');
                $excusedCount = $this->getAttendanceCountByStatus($teacherId, $subjectId, $viewType, $date, 'excused');
            } else {
                // Fallback with status IDs
                $presentCount = $this->getAttendanceCountByStatusId($teacherId, $subjectId, $viewType, $date, 1);
                $absentCount = $this->getAttendanceCountByStatusId($teacherId, $subjectId, $viewType, $date, 2);
                $lateCount = $this->getAttendanceCountByStatusId($teacherId, $subjectId, $viewType, $date, 3);
                $excusedCount = $this->getAttendanceCountByStatusId($teacherId, $subjectId, $viewType, $date, 4);
            }

            $presentData[] = $presentCount;
            $absentData[] = $absentCount;
            $lateData[] = $lateCount;
            $excusedData[] = $excusedCount;
        }

        return [
            'labels' => array_map(function($date) {
                return Carbon::parse($date)->format('M j');
            }, $days),
            'datasets' => [
                [
                    'label' => 'Present',
                    'backgroundColor' => '#4CAF50',
                    'data' => $presentData
                ],
                [
                    'label' => 'Absent',
                    'backgroundColor' => '#F44336',
                    'data' => $absentData
                ],
                [
                    'label' => 'Late',
                    'backgroundColor' => '#FF9800',
                    'data' => $lateData
                ],
                [
                    'label' => 'Excused',
                    'backgroundColor' => '#9E9E9E',
                    'data' => $excusedData
                ]
            ]
        ];
    }

    private function getWeeklyTrends($teacherId, $subjectId, $viewType, $hasStatusTable)
    {
        // Get last 4 weeks
        $weeks = [];
        for ($i = 3; $i >= 0; $i--) {
            $startOfWeek = Carbon::now()->subWeeks($i)->startOfWeek()->toDateString();
            $endOfWeek = Carbon::now()->subWeeks($i)->endOfWeek()->toDateString();
            $weeks[] = ['start' => $startOfWeek, 'end' => $endOfWeek, 'label' => 'Week ' . (4 - $i)];
        }

        $presentData = [];
        $absentData = [];
        $lateData = [];
        $excusedData = [];

        foreach ($weeks as $week) {
            if ($hasStatusTable) {
                $presentCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 'present');
                $absentCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 'absent');
                $lateCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 'late');
                $excusedCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 'excused');
            } else {
                $presentCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 1);
                $absentCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 2);
                $lateCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 3);
                $excusedCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $week['start'], $week['end'], 4);
            }

            $presentData[] = $presentCount;
            $absentData[] = $absentCount;
            $lateData[] = $lateCount;
            $excusedData[] = $excusedCount;
        }

        return [
            'labels' => array_column($weeks, 'label'),
            'datasets' => [
                [
                    'label' => 'Present',
                    'backgroundColor' => '#4CAF50',
                    'data' => $presentData
                ],
                [
                    'label' => 'Absent',
                    'backgroundColor' => '#F44336',
                    'data' => $absentData
                ],
                [
                    'label' => 'Late',
                    'backgroundColor' => '#FF9800',
                    'data' => $lateData
                ],
                [
                    'label' => 'Excused',
                    'backgroundColor' => '#9E9E9E',
                    'data' => $excusedData
                ]
            ]
        ];
    }

    private function getMonthlyTrends($teacherId, $subjectId, $viewType, $hasStatusTable)
    {
        // Get last 6 months
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = Carbon::now()->subMonths($i)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->subMonths($i)->endOfMonth()->toDateString();
            $label = Carbon::now()->subMonths($i)->format('M Y');
            $months[] = ['start' => $startOfMonth, 'end' => $endOfMonth, 'label' => $label];
        }

        $presentData = [];
        $absentData = [];
        $lateData = [];
        $excusedData = [];

        foreach ($months as $month) {
            if ($hasStatusTable) {
                $presentCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 'present');
                $absentCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 'absent');
                $lateCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 'late');
                $excusedCount = $this->getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 'excused');
            } else {
                $presentCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 1);
                $absentCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 2);
                $lateCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 3);
                $excusedCount = $this->getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $month['start'], $month['end'], 4);
            }

            $presentData[] = $presentCount;
            $absentData[] = $absentCount;
            $lateData[] = $lateCount;
            $excusedData[] = $excusedCount;
        }

        return [
            'labels' => array_column($months, 'label'),
            'datasets' => [
                [
                    'label' => 'Present',
                    'backgroundColor' => '#4CAF50',
                    'data' => $presentData
                ],
                [
                    'label' => 'Absent',
                    'backgroundColor' => '#F44336',
                    'data' => $absentData
                ],
                [
                    'label' => 'Late',
                    'backgroundColor' => '#FF9800',
                    'data' => $lateData
                ],
                [
                    'label' => 'Excused',
                    'backgroundColor' => '#9E9E9E',
                    'data' => $excusedData
                ]
            ]
        ];
    }

    private function getAttendanceCountByStatus($teacherId, $subjectId, $viewType, $date, $status)
    {
        $query = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->where('ases.teacher_id', $teacherId)
            ->where('ases.session_date', $date)
            ->where('ast.name', ucfirst($status));

        if ($viewType === 'subject' && $subjectId) {
            $query->where('ases.subject_id', $subjectId);
        }

        return $query->count();
    }

    private function getAttendanceCountByStatusId($teacherId, $subjectId, $viewType, $date, $statusId)
    {
        $query = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->where('ases.teacher_id', $teacherId)
            ->where('ases.session_date', $date)
            ->where('ar.attendance_status_id', $statusId);

        if ($viewType === 'subject' && $subjectId) {
            $query->where('ases.subject_id', $subjectId);
        }

        return $query->count();
    }

    private function getAttendanceCountByStatusRange($teacherId, $subjectId, $viewType, $startDate, $endDate, $status)
    {
        $query = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->where('ases.teacher_id', $teacherId)
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->where('ast.name', ucfirst($status));

        if ($viewType === 'subject' && $subjectId) {
            $query->where('ases.subject_id', $subjectId);
        }

        return $query->count();
    }

    private function getAttendanceCountByStatusIdRange($teacherId, $subjectId, $viewType, $startDate, $endDate, $statusId)
    {
        $query = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->where('ases.teacher_id', $teacherId)
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->where('ar.attendance_status_id', $statusId);

        if ($viewType === 'subject' && $subjectId) {
            $query->where('ases.subject_id', $subjectId);
        }

        return $query->count();
    }
}
