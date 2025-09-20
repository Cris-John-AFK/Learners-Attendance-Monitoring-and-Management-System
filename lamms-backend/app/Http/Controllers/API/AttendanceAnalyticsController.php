<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceAnalyticsController extends Controller
{
    /**
     * Get overall attendance overview for admin dashboard
     */
    public function getOverview(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::today()->toDateString());
            $dateTo = $request->get('date_to', Carbon::today()->toDateString());

            // Get total students across all grades
            $totalStudents = DB::table('students')
                ->join('student_section', 'students.id', '=', 'student_section.student_id')
                ->where('student_section.is_active', true)
                ->count();

            // Get attendance statistics for the date range
            $attendanceStats = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereBetween('ases.session_date', [$dateFrom, $dateTo])
                ->selectRaw('
                    COUNT(*) as total_records,
                    SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ast.code = \'L\' THEN 1 ELSE 0 END) as late_count
                ')
                ->first();

            // Calculate percentages
            $totalRecords = $attendanceStats->total_records ?: 1;
            $presentPercentage = round(($attendanceStats->present_count / $totalRecords) * 100, 1);
            $absentPercentage = round(($attendanceStats->absent_count / $totalRecords) * 100, 1);
            $latePercentage = round(($attendanceStats->late_count / $totalRecords) * 100, 1);

            // Get grade-level breakdown
            $gradeBreakdown = DB::table('grades as g')
                ->leftJoin('curriculum_grade as cg', 'g.id', '=', 'cg.grade_id')
                ->leftJoin('sections as s', 'cg.id', '=', 's.curriculum_grade_id')
                ->leftJoin('student_section as ss', 's.id', '=', 'ss.section_id')
                ->leftJoin('students as st', 'ss.student_id', '=', 'st.id')
                ->leftJoin('attendance_records as ar', function($join) use ($dateFrom, $dateTo) {
                    $join->on('st.id', '=', 'ar.student_id')
                         ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                         ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                         ->whereBetween('ases.session_date', [$dateFrom, $dateTo]);
                })
                ->where('ss.is_active', true)
                ->groupBy('g.id', 'g.name')
                ->selectRaw('
                    g.id as grade_id,
                    g.name as grade_name,
                    COUNT(DISTINCT st.id) as total_students,
                    COUNT(ar.id) as total_attendance_records,
                    SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ast.code = \'L\' THEN 1 ELSE 0 END) as late_count
                ')
                ->get()
                ->map(function($grade) {
                    $totalRecords = $grade->total_attendance_records ?: 1;
                    $grade->attendance_percentage = round(($grade->present_count / $totalRecords) * 100, 1);
                    return $grade;
                });

            // Get attendance trend for the last 7 days
            $attendanceTrend = DB::table('attendance_sessions as ases')
                ->leftJoin('attendance_records as ar', 'ases.id', '=', 'ar.attendance_session_id')
                ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereBetween('ases.session_date', [Carbon::now()->subDays(6)->toDateString(), Carbon::today()->toDateString()])
                ->groupBy('ases.session_date')
                ->selectRaw('
                    ases.session_date as date,
                    COUNT(ar.id) as total_records,
                    SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ast.code = \'L\' THEN 1 ELSE 0 END) as late_count
                ')
                ->orderBy('ases.session_date')
                ->get()
                ->map(function($day) {
                    $totalRecords = $day->total_records ?: 1;
                    $day->attendance_percentage = round(($day->present_count / $totalRecords) * 100, 1);
                    return $day;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_students' => $totalStudents,
                        'present_count' => $attendanceStats->present_count,
                        'absent_count' => $attendanceStats->absent_count,
                        'late_count' => $attendanceStats->late_count,
                        'present_percentage' => $presentPercentage,
                        'absent_percentage' => $absentPercentage,
                        'late_percentage' => $latePercentage,
                        'average_attendance' => $presentPercentage
                    ],
                    'grade_breakdown' => $gradeBreakdown,
                    'attendance_trend' => $attendanceTrend,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sections breakdown for a specific grade
     */
    public function getGradeDetails(Request $request, $gradeId)
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::today()->toDateString());
            $dateTo = $request->get('date_to', Carbon::today()->toDateString());

            // Get grade information
            $grade = DB::table('grades')->where('id', $gradeId)->first();
            
            if (!$grade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found'
                ], 404);
            }

            // Get sections in this grade with attendance data
            $sections = DB::table('sections as s')
                ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                ->leftJoin('student_section as ss', 's.id', '=', 'ss.section_id')
                ->leftJoin('students as st', 'ss.student_id', '=', 'st.id')
                ->leftJoin('attendance_records as ar', function($join) use ($dateFrom, $dateTo) {
                    $join->on('st.id', '=', 'ar.student_id')
                         ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                         ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                         ->whereBetween('ases.session_date', [$dateFrom, $dateTo]);
                })
                ->leftJoin('teachers as t', 's.homeroom_teacher_id', '=', 't.id')
                ->where('cg.grade_id', $gradeId)
                ->where('ss.is_active', true)
                ->groupBy('s.id', 's.name', 't.first_name', 't.last_name')
                ->selectRaw('
                    s.id as section_id,
                    s.name as section_name,
                    CONCAT(t.first_name, \' \', t.last_name) as homeroom_teacher,
                    COUNT(DISTINCT st.id) as total_students,
                    COUNT(ar.id) as total_attendance_records,
                    SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ast.code = \'L\' THEN 1 ELSE 0 END) as late_count
                ')
                ->get()
                ->map(function($section) {
                    $totalRecords = $section->total_attendance_records ?: 1;
                    $section->attendance_percentage = round(($section->present_count / $totalRecords) * 100, 1);
                    return $section;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'grade' => $grade,
                    'sections' => $sections,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grade details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students in a specific section with attendance details
     */
    public function getSectionDetails(Request $request, $sectionId)
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::today()->toDateString());
            $dateTo = $request->get('date_to', Carbon::today()->toDateString());

            // Get section information
            $section = DB::table('sections as s')
                ->leftJoin('grades as g', 's.grade_id', '=', 'g.id')
                ->leftJoin('teachers as t', 's.homeroom_teacher_id', '=', 't.id')
                ->where('s.id', $sectionId)
                ->select('s.*', 'g.name as grade_name', 
                        DB::raw('CONCAT(t.first_name, " ", t.last_name) as homeroom_teacher'))
                ->first();

            if (!$section) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found'
                ], 404);
            }

            // Get students in this section with their attendance records
            $students = DB::table('students as st')
                ->join('student_section as ss', 'st.id', '=', 'ss.student_id')
                ->leftJoin('attendance_records as ar', function($join) use ($dateFrom, $dateTo) {
                    $join->on('st.id', '=', 'ar.student_id')
                         ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                         ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                         ->whereBetween('ases.session_date', [$dateFrom, $dateTo]);
                })
                ->where('ss.section_id', $sectionId)
                ->where('ss.is_active', true)
                ->groupBy('st.id', 'st.first_name', 'st.last_name', 'st.student_id')
                ->selectRaw('
                    st.id,
                    st.student_id,
                    st.first_name,
                    st.last_name,
                    COUNT(ar.id) as total_attendance_records,
                    SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ast.code = \'L\' THEN 1 ELSE 0 END) as late_count,
                    MAX(CASE WHEN ast.code = \'P\' AND ar.arrival_time IS NOT NULL THEN ar.arrival_time END) as latest_time_in,
                    MAX(CASE WHEN ast.code = \'P\' AND ar.departure_time IS NOT NULL THEN ar.departure_time END) as latest_time_out,
                    MAX(CASE WHEN ases.session_date = CURRENT_DATE THEN ast.code END) as today_status
                ')
                ->get()
                ->map(function($student) {
                    $totalRecords = $student->total_attendance_records ?: 1;
                    $student->attendance_percentage = round(($student->present_count / $totalRecords) * 100, 1);
                    $student->full_name = $student->first_name . ' ' . $student->last_name;
                    
                    // Convert status codes to readable format
                    switch($student->today_status) {
                        case 'P': $student->today_status = 'present'; break;
                        case 'A': $student->today_status = 'absent'; break;
                        case 'L': $student->today_status = 'late'; break;
                        case 'E': $student->today_status = 'excused'; break;
                        default: $student->today_status = null;
                    }
                    
                    return $student;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'section' => $section,
                    'students' => $students,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
