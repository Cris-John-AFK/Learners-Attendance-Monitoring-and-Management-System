<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $totalStudents = DB::table('student_details')
                ->where('isActive', true)
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

            // Get grade-level breakdown by mapping sections to grades
            $gradeBreakdown = collect();
            
            // Get all grades
            $grades = DB::table('grades')->get();
            
            foreach ($grades as $grade) {
                // Map sections to grades based on section names
                $sectionConditions = [];
                if ($grade->code === 'K1') {
                    // Only "Kinder 1" grade gets both "Kinder One" and "Kinder Two" sections
                    $sectionConditions = ['Kinder One', 'Kinder Two'];
                } else if ($grade->code === 'K2') {
                    // "Kinder 2" grade gets no sections (empty)
                    $sectionConditions = [];
                } else {
                    // For other grades, use the grade level pattern
                    $sectionConditions = ["Grade {$grade->level} - Section A", "Grade {$grade->level} - Section B"];
                }
                
                // Get attendance data for this grade's sections
                if (empty($sectionConditions)) {
                    // No sections for this grade, return empty data
                    $gradeData = (object)[
                        'total_students' => 0,
                        'total_attendance_records' => 0,
                        'present_count' => 0,
                        'absent_count' => 0,
                        'late_count' => 0
                    ];
                } else {
                    $gradeData = DB::table('attendance_sessions as ases')
                        ->join('sections as s', 'ases.section_id', '=', 's.id')
                        ->leftJoin('attendance_records as ar', 'ases.id', '=', 'ar.attendance_session_id')
                        ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                        ->whereIn('s.name', $sectionConditions)
                        ->whereBetween('ases.session_date', [$dateFrom, $dateTo])
                        ->selectRaw('
                            COUNT(DISTINCT ar.student_id) as total_students,
                            COUNT(ar.id) as total_attendance_records,
                            SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count,
                            SUM(CASE WHEN ast.code = \'L\' THEN 1 ELSE 0 END) as late_count
                        ')
                        ->first();
                }
                
                $totalRecords = $gradeData->total_attendance_records ?: 1;
                $attendancePercentage = round(($gradeData->present_count / $totalRecords) * 100, 1);
                
                $gradeBreakdown->push((object)[
                    'grade_id' => $grade->id,
                    'grade_name' => $grade->name,
                    'total_students' => $gradeData->total_students ?: 0,
                    'total_attendance_records' => $gradeData->total_attendance_records ?: 0,
                    'present_count' => $gradeData->present_count ?: 0,
                    'absent_count' => $gradeData->absent_count ?: 0,
                    'late_count' => $gradeData->late_count ?: 0,
                    'attendance_percentage' => $attendancePercentage
                ]);
            }

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
            
            // Determine which sections belong to this grade
            $sectionConditions = [];
            if (str_contains($grade->code, 'K')) {
                // For kindergarten grades, include both "Kinder One" and "Kinder Two" sections
                $sectionConditions = ['Kinder One', 'Kinder Two'];
            } else {
                // For other grades, use the grade level pattern
                $sectionConditions = ["Grade {$grade->level} - Section A", "Grade {$grade->level} - Section B"];
            }
            
            $sections = DB::table('sections as s')
                ->leftJoin('attendance_sessions as ases', 's.id', '=', 'ases.section_id')
                ->leftJoin('attendance_records as ar', function($join) use ($dateFrom, $dateTo) {
                    $join->on('ases.id', '=', 'ar.attendance_session_id')
                         ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                         ->whereBetween('ases.session_date', [$dateFrom, $dateTo]);
                })
                ->leftJoin('teachers as t', 's.homeroom_teacher_id', '=', 't.id')
                ->whereIn('s.name', $sectionConditions)
                ->groupBy('s.id', 's.name', 't.first_name', 't.last_name')
                ->selectRaw('
                    s.id as section_id,
                    s.name as section_name,
                    CONCAT(t.first_name, \' \', t.last_name) as homeroom_teacher,
                    COUNT(DISTINCT ar.student_id) as total_students,
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

            Log::info("getSectionDetails called with sectionId: $sectionId, dateFrom: $dateFrom, dateTo: $dateTo");

            // Get section information (simplified)
            $section = DB::table('sections')
                ->where('id', $sectionId)
                ->first();

            if (!$section) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found'
                ], 404);
            }

            Log::info("Found section: " . json_encode($section));

            // Add grade name and homeroom teacher info
            $section->grade_name = str_contains($section->name, 'Kinder') ? 'Kinder 1' : 'Unknown';
            $section->homeroom_teacher = 'Not assigned';

            // Get students from attendance records (working version)
            $students = DB::table('attendance_sessions as ases')
                ->join('attendance_records as ar', 'ases.id', '=', 'ar.attendance_session_id')
                ->join('student_details as st', 'ar.student_id', '=', 'st.id')
                ->where('ases.section_id', $sectionId)
                ->whereBetween('ases.session_date', [$dateFrom, $dateTo])
                ->select('st.id', 'st.firstName as first_name', 'st.lastName as last_name', 'st.studentId as student_id')
                ->distinct()
                ->get()
                ->map(function($student) use ($sectionId) {
                    $student->full_name = $student->first_name . ' ' . $student->last_name;
                    
                    // Get real attendance data for this student
                    $attendanceData = DB::table('attendance_sessions as ases')
                        ->join('attendance_records as ar', 'ases.id', '=', 'ar.attendance_session_id')
                        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                        ->where('ases.section_id', $sectionId)
                        ->where('ar.student_id', $student->id)
                        ->selectRaw('
                            COUNT(*) as total_records,
                            SUM(CASE WHEN ast.code = \'P\' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN ast.code = \'A\' THEN 1 ELSE 0 END) as absent_count
                        ')
                        ->first();
                    
                    // Get today's attendance record with time information (Priority System)
                    // Check teacher attendance records first
                    $todayRecord = DB::table('attendance_sessions as ases')
                        ->join('attendance_records as ar', 'ases.id', '=', 'ar.attendance_session_id')
                        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                        ->where('ases.section_id', $sectionId)
                        ->where('ar.student_id', $student->id)
                        ->where('ases.session_date', Carbon::today()->toDateString())
                        ->select('ast.code as status', 'ar.arrival_time', 'ar.marked_at', 'ar.marking_method', DB::raw("'teacher' as source"))
                        ->orderBy('ar.created_at', 'desc')
                        ->first();
                    
                    // Also check gate attendance records
                    $gateRecord = DB::table('gate_attendance')
                        ->where('student_id', $student->id)
                        ->where('scan_date', Carbon::today()->toDateString())
                        ->where('type', 'check_in')
                        ->select(
                            DB::raw("'P' as status"), 
                            DB::raw('scan_time as arrival_time'), 
                            DB::raw('scan_time as marked_at'), 
                            DB::raw("'qr_gate' as marking_method"),
                            DB::raw("'gate' as source")
                        )
                        ->orderBy('scan_time', 'desc')
                        ->first();
                    
                    // Prioritize gate attendance over teacher attendance (gate is more accurate)
                    if ($gateRecord) {
                        $todayRecord = $gateRecord;
                    }
                    
                    $totalRecords = $attendanceData->total_records ?: 1;
                    $student->attendance_percentage = round(($attendanceData->present_count / $totalRecords) * 100, 1);
                    
                    // Determine today's status and time in using hybrid approach
                    if ($todayRecord && $todayRecord->status === 'P') {
                        $student->today_status = 'present';
                        
                        // Priority System for Time In:
                        // 1st: arrival_time (from QR/Gate or manual entry)
                        // 2nd: marked_at (fallback)
                        if ($todayRecord->arrival_time) {
                            $student->latest_time_in = $todayRecord->arrival_time;
                        } elseif ($todayRecord->marked_at) {
                            // Extract time from marked_at timestamp
                            $student->latest_time_in = Carbon::parse($todayRecord->marked_at)->format('H:i:s');
                        } else {
                            $student->latest_time_in = null;
                        }
                    } elseif ($todayRecord && $todayRecord->status === 'A') {
                        $student->today_status = 'absent';
                        $student->latest_time_in = null; // Will show "Not Present"
                    } elseif ($todayRecord && $todayRecord->status === 'L') {
                        $student->today_status = 'late';
                        // Same priority system for late arrivals
                        if ($todayRecord->arrival_time) {
                            $student->latest_time_in = $todayRecord->arrival_time;
                        } elseif ($todayRecord->marked_at) {
                            $student->latest_time_in = Carbon::parse($todayRecord->marked_at)->format('H:i:s');
                        } else {
                            $student->latest_time_in = null;
                        }
                    } else {
                        // No record for today
                        $student->today_status = 'no_record';
                        $student->latest_time_in = null;
                    }
                    
                    return $student;
                });

            Log::info("Found " . count($students) . " students");

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
            Log::error('Error in getSectionDetails: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
