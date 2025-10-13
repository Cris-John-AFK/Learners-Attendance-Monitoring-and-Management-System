<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\StudentSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceSessionController extends Controller
{
    /**
     * Get students for a specific teacher's section and subject
     */
    public function getStudentsForTeacherSubject(Request $request)
    {
        try {
            // Get parameters from request
            $teacherId = $request->query('teacher_id');
            $sectionId = $request->query('section_id');
            $subjectId = $request->query('subject_id');
            
            Log::info("AttendanceSessionController - Getting students", [
                'teacher_id' => $teacherId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId
            ]);
            
            Log::info("🔍 DEBUGGING: About to execute student query with filtering");
            
            // Validate required parameters
            if (empty($sectionId) || $sectionId === '' || $sectionId === 'null') {
                Log::warning("Invalid section_id provided", ['section_id' => $sectionId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Section ID is required',
                    'students' => [],
                    'count' => 0
                ], 400);
            }
            
            // First, get the section name
            $sectionName = DB::table('sections')
                ->where('id', $sectionId)
                ->value('name');

            Log::info("Section name retrieved", ['section_id' => $sectionId, 'section_name' => $sectionName]);

            // Get all active students in the section - ONLY from pivot table
            // EXCLUDE dropped out, transferred out, withdrawn, and deceased students
            $students = DB::table('student_details as sd')
                ->join('student_section as ss', function($join) use ($sectionId) {
                    $join->on('sd.id', '=', 'ss.student_id')
                         ->where('ss.section_id', '=', $sectionId)
                         ->where('ss.is_active', '=', 1);
                })
                ->join('sections as s', 'ss.section_id', '=', 's.id')
                ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
                // CRITICAL: Only include active students, explicitly exclude dropped out students
                ->where(function($query) {
                    $query->where(function($subQuery) {
                        $subQuery->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                                 ->orWhereNull('sd.enrollment_status');
                    })
                    ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased']);
                })
                ->select([
                    'sd.id',
                    'sd.firstName as first_name',
                    'sd.lastName as last_name',
                    'sd.middleName as middle_name',
                    'sd.lrn',
                    'sd.qr_code_path as qr_code',
                    'sd.gender',
                    'sd.age',
                    'sd.status',
                    'sd.enrollment_status',
                    's.name as section_name',
                    'g.name as grade_name',
                    DB::raw('(SELECT COUNT(*) FROM attendance_records ar 
                             INNER JOIN attendance_statuses ast ON ar.attendance_status_id = ast.id 
                             WHERE ar.student_id = sd.id AND ast.code = \'A\') as total_absences')
                ])
                ->distinct()
                ->orderBy('sd.lastName')
                ->orderBy('sd.firstName')
                ->get();

            // Log any dropped out students that were filtered out
            $allStudentsInSection = DB::table('student_details as sd')
                ->join('student_section as ss', function($join) use ($sectionId) {
                    $join->on('sd.id', '=', 'ss.student_id')
                         ->where('ss.section_id', '=', $sectionId)
                         ->where('ss.is_active', '=', 1);
                })
                ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.status')
                ->get();
            
            $droppedOutStudents = $allStudentsInSection->whereIn('status', ['Dropped Out', 'dropped_out', 'Transferred Out', 'transferred_out', 'Withdrawn', 'withdrawn', 'Deceased', 'deceased']);
            
            if ($droppedOutStudents->count() > 0) {
                Log::info("🚫 Filtered out non-enrolled students from attendance", [
                    'section_id' => $sectionId,
                    'filtered_count' => $droppedOutStudents->count(),
                    'filtered_students' => $droppedOutStudents->map(function($student) {
                        return $student->firstName . ' ' . $student->lastName . ' (' . $student->status . ')';
                    })->toArray()
                ]);
            }

            $totalInSection = $allStudentsInSection->count();
            $filteredOut = $droppedOutStudents->count();

            Log::info("Found students for section", [
                'section_id' => $sectionId, 
                'student_count' => $students->count(),
                'total_in_section' => $totalInSection,
                'filtered_out' => $filteredOut
            ]);

            // DEBUG: Log first few students with their enrollment status
            Log::info("🔍 DEBUGGING: First 5 students returned", [
                'students' => $students->take(5)->map(function($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->first_name . ' ' . $student->last_name,
                        'status' => $student->status,
                        'enrollment_status' => $student->enrollment_status
                    ];
                })->toArray()
            ]);

            return response()->json([
                'success' => true,
                'students' => $students,
                'count' => $students->count(),
                'section_id' => $sectionId,
                'subject_id' => $subjectId
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getStudentsForTeacherSubject', [
                'teacher_id' => $teacherId ?? null,
                'section_id' => $sectionId ?? null,
                'subject_id' => $subjectId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching students: ' . $e->getMessage(),
                'students' => [],
                'count' => 0
            ], 500);
        }
    }

    /**
     * Create or get today's attendance session
     */
    public function createSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'session_date' => 'required|date',
            'session_start_time' => 'required',
            'session_type' => 'in:regular,makeup,special'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if session already exists for today
            $existingSession = AttendanceSession::where([
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'session_date' => $request->session_date
            ])->first();

            if ($existingSession) {
                return response()->json([
                    'success' => true,
                    'session' => $existingSession,
                    'message' => 'Session already exists for today'
                ]);
            }

            // Create new session
            $session = AttendanceSession::create([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'session_date' => $request->session_date,
                'session_start_time' => $request->session_start_time,
                'session_type' => $request->session_type ?? 'regular',
                'status' => 'active',
                'metadata' => $request->metadata ?? null
            ]);

            return response()->json([
                'success' => true,
                'session' => $session,
                'message' => 'Session created successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark attendance for students in a session
     */
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|exists:attendance_sessions,id',
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:student_details,id',
            'attendance.*.attendance_status_id' => 'required|exists:attendance_statuses,id',
            'attendance.*.remarks' => 'nullable|string|max:255',
            'attendance.*.reason_id' => 'nullable|exists:attendance_reasons,id',
            'attendance.*.reason_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $session = AttendanceSession::findOrFail($request->session_id);
            $attendanceData = [];

            foreach ($request->attendance as $record) {
                $attendanceData[] = [
                    'session_id' => $request->session_id,
                    'student_id' => $record['student_id'],
                    'attendance_status_id' => $record['attendance_status_id'],
                    'remarks' => $record['remarks'] ?? null,
                    'reason_id' => $record['reason_id'] ?? null,
                    'reason_notes' => $record['reason_notes'] ?? null,
                    'marked_by' => $session->teacher_id,
                    'marked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Use upsert to handle duplicates
            AttendanceRecord::upsert(
                $attendanceData,
                ['session_id', 'student_id'], // Unique keys
                ['attendance_status_id', 'remarks', 'reason_id', 'reason_notes', 'marked_at', 'updated_at'] // Update these fields
            );

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked successfully',
                'records_processed' => count($attendanceData)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete an attendance session
     */
    public function completeSession(Request $request, $sessionId)
    {
        try {
            $session = AttendanceSession::findOrFail($sessionId);
            
            $session->update([
                'status' => 'completed',
                'session_end_time' => now()->format('H:i:s')
            ]);

            // Get session statistics
            $stats = DB::table('attendance_records as ar')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->where('ar.session_id', $sessionId)
                ->select('ast.status_name', DB::raw('count(*) as count'))
                ->groupBy('ast.status_name')
                ->pluck('count', 'status_name')
                ->toArray();

            return response()->json([
                'success' => true,
                'session' => $session,
                'statistics' => $stats,
                'message' => 'Session completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary for dashboard
     */
    public function getAttendanceSummary(Request $request)
    {
        try {
            $teacherId = $request->teacher_id;
            $subjectId = $request->subject_id;
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;

            // Get total students in sections taught by this teacher for this subject
            $totalStudents = DB::table('student_section as ss')
                ->join('teacher_section_subject as tss', 'ss.section_id', '=', 'tss.section_id')
                ->where('tss.teacher_id', $teacherId)
                ->where('tss.subject_id', $subjectId)
                ->where('ss.is_active', true)
                ->where('ss.status', 'enrolled')
                ->distinct('ss.student_id')
                ->count();

            // Get attendance statistics
            $attendanceStats = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->where('ases.teacher_id', $teacherId)
                ->where('ases.subject_id', $subjectId)
                ->whereBetween('ases.session_date', [$dateFrom, $dateTo])
                ->select('ast.status_name', DB::raw('count(*) as count'))
                ->groupBy('ast.status_name')
                ->pluck('count', 'status_name')
                ->toArray();

            $presentCount = $attendanceStats['Present'] ?? 0;
            $absentCount = $attendanceStats['Absent'] ?? 0;
            $totalRecords = $presentCount + $absentCount;

            $averageAttendance = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totalStudents' => $totalStudents,
                    'averageAttendance' => $averageAttendance,
                    'studentsWithWarning' => 0, // Will implement later
                    'studentsWithCritical' => 0, // Will implement later
                    'presentCount' => $presentCount,
                    'absentCount' => $absentCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting attendance summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher data by ID
     */
    public function getTeacherData($teacherId)
    {
        try {
            $teacher = DB::table('teachers as t')
                ->join('users as u', 't.user_id', '=', 'u.id')
                ->where('t.id', $teacherId)
                ->select([
                    't.id',
                    't.first_name',
                    't.last_name',
                    't.phone_number',
                    't.gender',
                    't.is_head_teacher',
                    'u.email',
                    'u.username'
                ])
                ->first();

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'teacher' => $teacher
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting teacher data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher assignments (sections and subjects)
     */
    public function getTeacherAssignments($teacherId)
    {
        try {
            $assignments = DB::table('teacher_section_subject as tss')
                ->join('sections as s', 'tss.section_id', '=', 's.id')
                ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
                ->where('tss.teacher_id', $teacherId)
                ->where('tss.is_active', true)
                ->select([
                    'tss.id as assignment_id',
                    'tss.section_id',
                    'tss.subject_id',
                    'tss.role',
                    'tss.is_primary',
                    's.name as section_name',
                    'sub.name as subject_name',
                    'sub.code as subject_code'
                ])
                ->get();

            // Group by section
            $groupedAssignments = $assignments->groupBy('section_id')->map(function ($sectionAssignments) {
                $section = $sectionAssignments->first();
                return [
                    'section_id' => $section->section_id,
                    'section_name' => $section->section_name,
                    'subjects' => $sectionAssignments->map(function ($assignment) {
                        return [
                            'subject_id' => $assignment->subject_id,
                            'subject_name' => $assignment->subject_name,
                            'subject_code' => $assignment->subject_code,
                            'role' => $assignment->role,
                            'is_primary' => $assignment->is_primary
                        ];
                    })->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'assignments' => $groupedAssignments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting teacher assignments: ' . $e->getMessage()
            ], 500);
        }
    }
}
