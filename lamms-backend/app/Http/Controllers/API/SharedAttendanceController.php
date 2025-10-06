<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SharedAttendanceController extends Controller
{
    /**
     * Get all students with their complete details and attendance records
     * This endpoint can be shared with groupmates for external integrations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStudentsWithAttendance(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', Carbon::now()->toDateString());
            $sectionId = $request->input('section_id');
            $gradeLevel = $request->input('grade_level');
            
            Log::info('Shared API - Getting all students with attendance', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'section_id' => $sectionId,
                'grade_level' => $gradeLevel
            ]);

            // Build student query with filters
            $query = Student::with(['sections'])
                ->select('student_details.*');

            // Filter by section if provided
            if ($sectionId) {
                $query->join('student_section', 'student_details.id', '=', 'student_section.student_id')
                    ->where('student_section.section_id', $sectionId)
                    ->where('student_section.is_active', true);
            }

            // Filter by grade level if provided
            if ($gradeLevel) {
                $query->where('student_details.gradeLevel', $gradeLevel);
            }

            $students = $query->get();

            // Get attendance records for each student
            $studentsWithAttendance = $students->map(function ($student) use ($dateFrom, $dateTo) {
                // Get attendance records
                $attendanceRecords = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                    ->leftJoin('subjects', 'ases.subject_id', '=', 'subjects.id')
                    ->leftJoin('sections', 'ases.section_id', '=', 'sections.id')
                    ->where('ar.student_id', $student->id)
                    ->whereBetween('ases.session_date', [$dateFrom, $dateTo])
                    ->select(
                        'ases.session_date',
                        'ar.arrival_time',
                        'ar.departure_time',
                        'ast.name as status',
                        'ast.code as status_code',
                        'subjects.name as subject_name',
                        'sections.name as section_name',
                        'ar.remarks'
                    )
                    ->orderBy('ases.session_date', 'desc')
                    ->get();

                // Calculate attendance statistics
                $totalPresent = $attendanceRecords->where('status_code', 'P')->count();
                $totalAbsent = $attendanceRecords->where('status_code', 'A')->count();
                $totalLate = $attendanceRecords->where('status_code', 'L')->count();
                $totalExcused = $attendanceRecords->where('status_code', 'E')->count();
                $totalRecords = $attendanceRecords->count();
                
                $attendanceRate = $totalRecords > 0 
                    ? round(($totalPresent / $totalRecords) * 100, 2) 
                    : 0;

                return [
                    'student_id' => $student->id,
                    'student_number' => $student->studentId ?? $student->lrn,
                    'lrn' => $student->lrn,
                    'name' => trim(($student->firstName ?? '') . ' ' . ($student->middleName ?? '') . ' ' . ($student->lastName ?? '')),
                    'first_name' => $student->firstName,
                    'middle_name' => $student->middleName,
                    'last_name' => $student->lastName,
                    'grade_level' => $student->gradeLevel,
                    'section' => $student->section,
                    'email' => $student->email,
                    'gender' => $student->gender ?? $student->sex,
                    'birthdate' => $student->birthdate,
                    'contact_number' => $student->contactInfo,
                    'parent_contact' => $student->parentContact,
                    'address' => $student->address ?? $student->currentAddress,
                    'attendance_summary' => [
                        'total_records' => $totalRecords,
                        'present' => $totalPresent,
                        'absent' => $totalAbsent,
                        'late' => $totalLate,
                        'excused' => $totalExcused,
                        'attendance_rate' => $attendanceRate,
                        'date_range' => [
                            'from' => $dateFrom,
                            'to' => $dateTo
                        ]
                    ],
                    'attendance_records' => $attendanceRecords
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $studentsWithAttendance,
                'meta' => [
                    'total_students' => $studentsWithAttendance->count(),
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ],
                    'filters' => [
                        'section_id' => $sectionId,
                        'grade_level' => $gradeLevel
                    ]
                ],
                'generated_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Shared API - Error getting students with attendance: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student attendance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific student details with complete attendance history
     * 
     * @param int $studentId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentDetails($studentId, Request $request)
    {
        try {
            $dateFrom = $request->input('date_from', Carbon::now()->subMonths(3)->toDateString());
            $dateTo = $request->input('date_to', Carbon::now()->toDateString());

            $student = Student::with(['sections'])->find($studentId);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Get detailed attendance records
            $attendanceRecords = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->leftJoin('subjects', 'ases.subject_id', '=', 'subjects.id')
                ->leftJoin('sections', 'ases.section_id', '=', 'sections.id')
                ->leftJoin('teachers', 'ases.teacher_id', '=', 'teachers.id')
                ->where('ar.student_id', $studentId)
                ->whereBetween('ases.session_date', [$dateFrom, $dateTo])
                ->select(
                    'ases.session_date',
                    'ar.arrival_time',
                    'ar.departure_time',
                    'ast.name as status',
                    'ast.code as status_code',
                    'subjects.name as subject_name',
                    'sections.name as section_name',
                    DB::raw("CONCAT(teachers.first_name, ' ', teachers.last_name) as teacher_name"),
                    'ar.remarks',
                    'ar.created_at as marked_at'
                )
                ->orderBy('ases.session_date', 'desc')
                ->get();

            // Calculate statistics
            $totalPresent = $attendanceRecords->where('status_code', 'P')->count();
            $totalAbsent = $attendanceRecords->where('status_code', 'A')->count();
            $totalLate = $attendanceRecords->where('status_code', 'L')->count();
            $totalExcused = $attendanceRecords->where('status_code', 'E')->count();
            $totalRecords = $attendanceRecords->count();
            
            $attendanceRate = $totalRecords > 0 
                ? round(($totalPresent / $totalRecords) * 100, 2) 
                : 0;

            // Get consecutive absence count
            $consecutiveAbsences = 0;
            $tempCount = 0;
            foreach ($attendanceRecords->sortBy('session_date') as $record) {
                if ($record->status_code === 'A') {
                    $tempCount++;
                    $consecutiveAbsences = max($consecutiveAbsences, $tempCount);
                } else {
                    $tempCount = 0;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'student_info' => [
                        'student_id' => $student->id,
                        'student_number' => $student->studentId ?? $student->lrn,
                        'lrn' => $student->lrn,
                        'name' => trim(($student->firstName ?? '') . ' ' . ($student->middleName ?? '') . ' ' . ($student->lastName ?? '')),
                        'first_name' => $student->firstName,
                        'middle_name' => $student->middleName,
                        'last_name' => $student->lastName,
                        'grade_level' => $student->gradeLevel,
                        'section' => $student->section,
                        'email' => $student->email,
                        'gender' => $student->gender ?? $student->sex,
                        'birthdate' => $student->birthdate,
                        'age' => $student->age,
                        'contact_number' => $student->contactInfo,
                        'parent_name' => $student->parentName,
                        'parent_contact' => $student->parentContact,
                        'address' => $student->address ?? $student->currentAddress,
                        'enrollment_date' => $student->enrollmentDate,
                        'status' => $student->status
                    ],
                    'attendance_summary' => [
                        'total_records' => $totalRecords,
                        'present' => $totalPresent,
                        'absent' => $totalAbsent,
                        'late' => $totalLate,
                        'excused' => $totalExcused,
                        'attendance_rate' => $attendanceRate,
                        'consecutive_absences' => $consecutiveAbsences,
                        'date_range' => [
                            'from' => $dateFrom,
                            'to' => $dateTo
                        ]
                    ],
                    'attendance_records' => $attendanceRecords
                ],
                'generated_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Shared API - Error getting student details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary statistics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttendanceSummary(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', Carbon::now()->toDateString());
            $sectionId = $request->input('section_id');
            $gradeLevel = $request->input('grade_level');

            // Build query
            $query = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->join('student_details as s', 'ar.student_id', '=', 's.id')
                ->whereBetween('ases.session_date', [$dateFrom, $dateTo]);

            // Apply filters
            if ($sectionId) {
                $query->where('ases.section_id', $sectionId);
            }

            if ($gradeLevel) {
                $query->where('s.gradeLevel', $gradeLevel);
            }

            // Get statistics
            $statistics = $query->select(
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN ast.code = 'P' THEN 1 ELSE 0 END) as total_present"),
                DB::raw("SUM(CASE WHEN ast.code = 'A' THEN 1 ELSE 0 END) as total_absent"),
                DB::raw("SUM(CASE WHEN ast.code = 'L' THEN 1 ELSE 0 END) as total_late"),
                DB::raw("SUM(CASE WHEN ast.code = 'E' THEN 1 ELSE 0 END) as total_excused"),
                DB::raw('COUNT(DISTINCT ar.student_id) as unique_students'),
                DB::raw('COUNT(DISTINCT ases.session_date) as total_days')
            )->first();

            $attendanceRate = $statistics->total_records > 0
                ? round(($statistics->total_present / $statistics->total_records) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $statistics->total_records,
                    'total_present' => $statistics->total_present,
                    'total_absent' => $statistics->total_absent,
                    'total_late' => $statistics->total_late,
                    'total_excused' => $statistics->total_excused,
                    'unique_students' => $statistics->unique_students,
                    'total_days' => $statistics->total_days,
                    'attendance_rate' => $attendanceRate,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ],
                    'filters' => [
                        'section_id' => $sectionId,
                        'grade_level' => $gradeLevel
                    ]
                ],
                'generated_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Shared API - Error getting attendance summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily attendance report
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyAttendance(Request $request)
    {
        try {
            $date = $request->input('date', Carbon::now()->toDateString());
            $sectionId = $request->input('section_id');

            $query = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->join('student_details as s', 'ar.student_id', '=', 's.id')
                ->leftJoin('subjects', 'ases.subject_id', '=', 'subjects.id')
                ->leftJoin('sections', 'ases.section_id', '=', 'sections.id')
                ->where('ases.session_date', $date);

            if ($sectionId) {
                $query->where('ases.section_id', $sectionId);
            }

            $records = $query->select(
                's.id as student_id',
                DB::raw('CONCAT(s."firstName", \' \', COALESCE(s."middleName", \'\'), \' \', s."lastName") as student_name'),
                's.lrn',
                DB::raw('s."gradeLevel" as gradeLevel'),
                'sections.name as section_name',
                'subjects.name as subject_name',
                'ast.name as status',
                'ast.code as status_code',
                'ar.arrival_time',
                'ar.departure_time',
                'ar.remarks'
            )->orderBy(DB::raw('s."lastName"'))->get();

            return response()->json([
                'success' => true,
                'data' => $records,
                'meta' => [
                    'date' => $date,
                    'total_records' => $records->count(),
                    'section_id' => $sectionId
                ],
                'generated_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Shared API - Error getting daily attendance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
