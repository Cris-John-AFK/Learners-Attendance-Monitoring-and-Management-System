<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceStatus;
use App\Models\Student;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Get attendance statuses
     */
    public function getAttendanceStatuses()
    {
        try {
            $statuses = AttendanceStatus::active()->ordered()->get();
            return response()->json($statuses);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance statuses: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch attendance statuses'], 500);
        }
    }

    /**
     * Get attendance trends for teacher dashboard
     */
    public function getAttendanceTrends(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'period' => 'required|in:daily,weekly,monthly',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $teacherId = $request->teacher_id;
            $subjectId = $request->subject_id;
            $period = $request->period;
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);

            // Get teacher's sections for this subject
            $sections = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacherId)
                ->where('subject_id', $subjectId)
                ->where('is_active', true)
                ->pluck('section_id');

            if ($sections->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => [],
                        'datasets' => []
                    ],
                    'message' => 'No sections assigned to this teacher for the selected subject'
                ]);
            }

            // Get attendance data based on period
            $attendanceData = $this->getAttendanceDataByPeriod($sections, $subjectId, $dateFrom, $dateTo, $period);

            return response()->json([
                'success' => true,
                'data' => $attendanceData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching attendance trends: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch attendance trends'], 500);
        }
    }

    /**
     * Get attendance summary for teacher dashboard
     */
    public function getAttendanceSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $teacherId = $request->teacher_id;
            $subjectId = $request->subject_id;
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);

            // Get teacher's sections
            $sectionsQuery = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacherId)
                ->where('is_active', true);

            if ($subjectId) {
                $sectionsQuery->where('subject_id', $subjectId);
            }

            $sections = $sectionsQuery->pluck('section_id');

            if ($sections->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'totalStudents' => 0,
                        'averageAttendance' => 0,
                        'studentsWithWarning' => 0,
                        'studentsWithCritical' => 0
                    ]
                ]);
            }

            // Get total students in these sections
            $totalStudents = DB::table('student_section')
                ->whereIn('section_id', $sections)
                ->where('is_active', true)
                ->distinct('student_id')
                ->count();

            // Get attendance statistics
            $attendanceQuery = Attendance::whereIn('section_id', $sections)
                ->whereBetween('date', [$dateFrom, $dateTo]);

            if ($subjectId) {
                $attendanceQuery->where('subject_id', $subjectId);
            }

            $totalAttendanceRecords = $attendanceQuery->count();
            $presentCount = $attendanceQuery->where('status', 'present')->count();

            // Calculate average attendance percentage
            $averageAttendance = $totalAttendanceRecords > 0 ?
                round(($presentCount / $totalAttendanceRecords) * 100, 1) : 0;

            // Count students with attendance issues
            $studentAbsenceCounts = $attendanceQuery
                ->where('status', 'absent')
                ->select('student_id', DB::raw('COUNT(*) as absence_count'))
                ->groupBy('student_id')
                ->get();

            $studentsWithWarning = $studentAbsenceCounts->where('absence_count', '>=', 3)->where('absence_count', '<', 5)->count();
            $studentsWithCritical = $studentAbsenceCounts->where('absence_count', '>=', 5)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'totalStudents' => $totalStudents,
                    'averageAttendance' => $averageAttendance,
                    'studentsWithWarning' => $studentsWithWarning,
                    'studentsWithCritical' => $studentsWithCritical
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching attendance summary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch attendance summary'], 500);
        }
    }

    /**
     * Helper method to get attendance data by period
     */
    private function getAttendanceDataByPeriod($sections, $subjectId, $dateFrom, $dateTo, $period)
    {
        $labels = [];
        $presentData = [];
        $absentData = [];
        $lateData = [];

        switch ($period) {
            case 'daily':
                // Group by day
                $current = $dateFrom->copy();
                while ($current <= $dateTo) {
                    $labels[] = $current->format('M j');

                    $dayAttendance = Attendance::whereIn('section_id', $sections)
                        ->where('subject_id', $subjectId)
                        ->whereDate('date', $current)
                        ->join('attendance_statuses', 'attendances.attendance_status_id', '=', 'attendance_statuses.id')
                        ->selectRaw("
                            SUM(CASE WHEN attendance_statuses.code = 'P' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN attendance_statuses.code = 'A' THEN 1 ELSE 0 END) as absent_count,
                            SUM(CASE WHEN attendance_statuses.code = 'L' THEN 1 ELSE 0 END) as late_count
                        ")
                        ->first();

                    $presentData[] = $dayAttendance->present_count ?? 0;
                    $absentData[] = $dayAttendance->absent_count ?? 0;
                    $lateData[] = $dayAttendance->late_count ?? 0;

                    $current->addDay();
                }
                break;

            case 'weekly':
                // Group by week
                $current = $dateFrom->copy()->startOfWeek();
                while ($current <= $dateTo) {
                    $weekEnd = $current->copy()->endOfWeek();
                    if ($weekEnd > $dateTo) $weekEnd = $dateTo;

                    $labels[] = $current->format('M j') . ' - ' . $weekEnd->format('M j');

                    $weekAttendance = Attendance::whereIn('section_id', $sections)
                        ->where('subject_id', $subjectId)
                        ->whereBetween('date', [$current, $weekEnd])
                        ->join('attendance_statuses', 'attendances.attendance_status_id', '=', 'attendance_statuses.id')
                        ->selectRaw("
                            SUM(CASE WHEN attendance_statuses.code = 'P' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN attendance_statuses.code = 'A' THEN 1 ELSE 0 END) as absent_count,
                            SUM(CASE WHEN attendance_statuses.code = 'L' THEN 1 ELSE 0 END) as late_count
                        ")
                        ->first();

                    $presentData[] = $weekAttendance->present_count ?? 0;
                    $absentData[] = $weekAttendance->absent_count ?? 0;
                    $lateData[] = $weekAttendance->late_count ?? 0;

                    $current->addWeek();
                }
                break;

            case 'monthly':
                // Group by month
                $current = $dateFrom->copy()->startOfMonth();
                while ($current <= $dateTo) {
                    $monthEnd = $current->copy()->endOfMonth();
                    if ($monthEnd > $dateTo) $monthEnd = $dateTo;

                    $labels[] = $current->format('M Y');

                    $monthAttendance = Attendance::whereIn('section_id', $sections)
                        ->where('subject_id', $subjectId)
                        ->whereBetween('date', [$current, $monthEnd])
                        ->join('attendance_statuses', 'attendances.attendance_status_id', '=', 'attendance_statuses.id')
                        ->selectRaw("
                            SUM(CASE WHEN attendance_statuses.code = 'P' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN attendance_statuses.code = 'A' THEN 1 ELSE 0 END) as absent_count,
                            SUM(CASE WHEN attendance_statuses.code = 'L' THEN 1 ELSE 0 END) as late_count
                        ")
                        ->first();

                    $presentData[] = $monthAttendance->present_count ?? 0;
                    $absentData[] = $monthAttendance->absent_count ?? 0;
                    $lateData[] = $monthAttendance->late_count ?? 0;

                    $current->addMonth();
                }
                break;
        }

        // Prepare data in the format expected by frontend
        $chartData = [];
        for ($i = 0; $i < count($labels); $i++) {
            $chartData[] = [
                'label' => $labels[$i],
                'present_count' => $presentData[$i] ?? 0,
                'absent_count' => $absentData[$i] ?? 0,
                'late_count' => $lateData[$i] ?? 0
            ];
        }

        return $chartData;
    }

    /**
     * Get attendance for a specific section, subject and date
     */
    public function getAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $sectionId = $request->section_id;
            $subjectId = $request->subject_id;
            $date = $request->date;

            // Get all students in the section
            $section = Section::with(['activeStudents'])->findOrFail($sectionId);
            $students = $section->activeStudents;

            // Get existing attendance records for this date, section, and subject
            $existingAttendance = Attendance::with(['attendanceStatus'])
                ->forDate($date)
                ->forSection($sectionId)
                ->forSubject($subjectId)
                ->get()
                ->keyBy('student_id');

            // Prepare attendance data
            $attendanceData = [];
            foreach ($students as $student) {
                $attendance = $existingAttendance->get($student->id);

                $attendanceData[] = [
                    'id' => $attendance ? $attendance->id : null,
                    'student_id' => $student->id,
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name ?? $student->firstName . ' ' . $student->lastName,
                        'firstName' => $student->firstName,
                        'lastName' => $student->lastName,
                        'studentId' => $student->studentId ?? $student->student_id
                    ],
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId,
                    'date' => $date,
                    'status' => $attendance ? $attendance->status : null,
                    'attendance_status' => $attendance && $attendance->attendanceStatus ? [
                        'id' => $attendance->attendanceStatus->id,
                        'code' => $attendance->attendanceStatus->code,
                        'name' => $attendance->attendanceStatus->name,
                        'color' => $attendance->attendanceStatus->color,
                        'background_color' => $attendance->attendanceStatus->background_color
                    ] : null,
                    'time_in' => $attendance ? $attendance->time_in : null,
                    'remarks' => $attendance ? $attendance->remarks : null,
                    'marked_at' => $attendance ? $attendance->marked_at : null
                ];
            }

            return response()->json([
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name
                ],
                'subject' => Subject::find($subjectId),
                'date' => $date,
                'attendance' => $attendanceData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch attendance'], 500);
        }
    }

    /**
     * Mark attendance for multiple students
     */
    public function markAttendance(Request $request)
    {
        // Handle subject_id conversion from string to integer if needed
        $requestData = $request->all();
        if (isset($requestData['subject_id']) && !is_numeric($requestData['subject_id'])) {
            $subject = \App\Models\Subject::where('name', 'ILIKE', $requestData['subject_id'])
                ->orWhere('code', 'ILIKE', $requestData['subject_id'])
                ->first();
            
            if ($subject) {
                $requestData['subject_id'] = $subject->id;
                Log::info('Converted subject identifier to ID in mark attendance', [
                    'original' => $request->input('subject_id'),
                    'resolved_id' => $subject->id,
                    'subject_name' => $subject->name
                ]);
            } else {
                Log::error('Subject not found for mark attendance', [
                    'subject_identifier' => $requestData['subject_id']
                ]);
                return response()->json([
                    'message' => 'Subject not found',
                    'error' => 'Invalid subject identifier: ' . $requestData['subject_id']
                ], 422);
            }
        }

        $validator = Validator::make($requestData, [
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',  // Made nullable for homeroom/general attendance
            'teacher_id' => 'required|exists:teachers,id',
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:student_details,id',
            'attendance.*.attendance_status_id' => 'required|exists:attendance_statuses,id',
            'attendance.*.remarks' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $sectionId = $requestData['section_id'];
            $subjectId = $requestData['subject_id'];
            $teacherId = $requestData['teacher_id'];
            $date = $requestData['date'];
            $attendanceData = $requestData['attendance'];

            $savedAttendance = [];

            foreach ($attendanceData as $attendance) {
                $attendanceStatus = AttendanceStatus::find($attendance['attendance_status_id']);

                // Map status codes to enum values for backward compatibility
                $statusMapping = [
                    'P' => 'present',
                    'A' => 'absent',
                    'L' => 'late',
                    'E' => 'excused'
                ];
                $enumStatus = $statusMapping[$attendanceStatus->code] ?? 'present';

                $attendanceRecord = Attendance::updateOrCreate(
                    [
                        'student_id' => $attendance['student_id'],
                        'section_id' => $sectionId,
                        'subject_id' => $subjectId,
                        'date' => $date
                    ],
                    [
                        'teacher_id' => $teacherId,
                        'status' => $enumStatus, // Use enum value for constraint compatibility
                        'attendance_status_id' => $attendance['attendance_status_id'],
                        'time_in' => now(),
                        'remarks' => $attendance['remarks'] ?? null,
                        'marked_at' => now()
                    ]
                );

                $savedAttendance[] = $attendanceRecord->load(['student', 'attendanceStatus']);
            }

            return response()->json([
                'message' => 'Attendance marked successfully',
                'attendance' => $savedAttendance
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error marking attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark attendance'], 500);
        }
    }

    /**
     * Mark single student attendance
     */
    public function markSingleAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:student_details,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',  // Made nullable for homeroom/general attendance
            'teacher_id' => 'required|exists:teachers,id',
            'attendance_status_id' => 'required|exists:attendance_statuses,id',
            'date' => 'required|date',
            'remarks' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $attendanceStatus = AttendanceStatus::find($request->attendance_status_id);

            // Map status codes to enum values for backward compatibility
            $statusMapping = [
                'P' => 'present',
                'A' => 'absent',
                'L' => 'late',
                'E' => 'excused'
            ];
            $enumStatus = $statusMapping[$attendanceStatus->code] ?? 'present';

            $attendance = Attendance::updateOrCreate(
                [
                    'student_id' => $request->student_id,
                    'section_id' => $request->section_id,
                    'subject_id' => $request->subject_id,
                    'date' => $request->date
                ],
                [
                    'teacher_id' => $request->teacher_id,
                    'status' => $enumStatus, // Use enum value for constraint compatibility
                    'attendance_status_id' => $request->attendance_status_id,
                    'time_in' => now(),
                    'remarks' => $request->remarks,
                    'marked_at' => now()
                ]
            );

            return response()->json([
                'message' => 'Attendance marked successfully',
                'attendance' => $attendance->load(['student', 'attendanceStatus'])
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error marking single attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark attendance'], 500);
        }
    }

    /**
     * Get attendance reports for a section
     */
    public function getAttendanceReport(Request $request, $sectionId)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'subject_id' => 'nullable|exists:subjects,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? Carbon::now()->endOfMonth()->toDateString();
            $subjectId = $request->subject_id;

            $query = Attendance::with(['student', 'subject', 'attendanceStatus'])
                ->forSection($sectionId)
                ->whereBetween('date', [$startDate, $endDate]);

            if ($subjectId) {
                $query->forSubject($subjectId);
            }

            $attendances = $query->orderBy('date')
                ->orderBy('student_id')
                ->get();

            // Group by student
            $reportData = $attendances->groupBy('student_id')->map(function ($studentAttendances) {
                $student = $studentAttendances->first()->student;
                $attendanceRecords = $studentAttendances->groupBy('date')->map(function ($dateAttendances) {
                    return $dateAttendances->groupBy('subject_id');
                });

                return [
                    'student' => $student,
                    'attendance_records' => $attendanceRecords
                ];
            });

            return response()->json([
                'section_id' => $sectionId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'subject_id' => $subjectId,
                'report' => $reportData
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating attendance report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate attendance report'], 500);
        }
    }

    /**
     * Get teacher assignments (sections and subjects)
     */
    public function getTeacherAssignments($teacherId)
    {
        try {
            $teacher = Teacher::findOrFail($teacherId);

            // Get teacher assignments from teacher_section_subject table
            $assignments = DB::table('teacher_section_subject')
                ->join('sections', 'teacher_section_subject.section_id', '=', 'sections.id')
                ->join('subjects', 'teacher_section_subject.subject_id', '=', 'subjects.id')
                ->leftJoin('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
                ->leftJoin('grades', 'curriculum_grade.grade_id', '=', 'grades.id')
                ->where('teacher_section_subject.teacher_id', $teacherId)
                ->where('teacher_section_subject.is_active', true)
                ->select(
                    'teacher_section_subject.id as assignment_id',
                    'sections.id as section_id',
                    'sections.name as section_name',
                    'subjects.id as subject_id',
                    'subjects.name as subject_name',
                    'grades.id as grade_id',
                    'grades.name as grade_name',
                    'teacher_section_subject.role',
                    'teacher_section_subject.is_primary'
                )
                ->get()
                ->groupBy('section_id');

            $result = [];
            foreach ($assignments as $sectionId => $sectionAssignments) {
                $firstAssignment = $sectionAssignments->first();

                $sectionData = [
                    'section_id' => $sectionId,
                    'section_name' => $firstAssignment->section_name,
                    'grade_id' => $firstAssignment->grade_id,
                    'grade_name' => $firstAssignment->grade_name,
                    'is_homeroom_teacher' => $sectionAssignments->contains('role', 'homeroom'),
                    'subjects' => []
                ];

                foreach ($sectionAssignments as $assignment) {
                    $sectionData['subjects'][] = [
                        'assignment_id' => $assignment->assignment_id,
                        'subject_id' => $assignment->subject_id,
                        'subject_name' => $assignment->subject_name,
                        'role' => $assignment->role,
                        'is_primary' => $assignment->is_primary
                    ];
                }

                $result[] = $sectionData;
            }

            return response()->json([
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->firstName . ' ' . $teacher->lastName,
                    'email' => $teacher->email
                ],
                'assignments' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching teacher assignments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch teacher assignments'], 500);
        }
    }

    /**
     * Get students for a specific teacher, section, and subject
     */
    public function getStudentsForTeacherSubject($teacherId, $sectionId, $subjectId)
    {
        try {
            // Verify teacher has assignment to this section and subject
            $assignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacherId)
                ->where('section_id', $sectionId)
                ->where(function($query) use ($subjectId) {
                    $query->where('subject_id', $subjectId)
                          ->orWhere('role', 'homeroom'); // Allow homeroom teachers to access any subject
                })
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return response()->json(['error' => 'Teacher not assigned to this section and subject'], 403);
            }

            // Get students in the section
            $section = Section::with(['activeStudents'])->findOrFail($sectionId);
            $students = $section->activeStudents;

            $studentsData = $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name ?? $student->firstName . ' ' . $student->lastName,
                    'firstName' => $student->firstName,
                    'lastName' => $student->lastName,
                    'studentId' => $student->studentId ?? $student->student_id
                ];
            });

            return response()->json([
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name
                ],
                'subject' => Subject::find($subjectId),
                'students' => $studentsData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching students for teacher subject: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch students'], 500);
        }
    }

    /**
     * Mark attendance for teacher-specific endpoint
     */
    public function markTeacherAttendance(Request $request, $teacherId)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:student_details,id',
            'attendance.*.attendance_status_id' => 'required|exists:attendance_statuses,id',
            'attendance.*.remarks' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Verify teacher has assignment to this section and subject
            $assignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacherId)
                ->where('section_id', $request->section_id)
                ->where('subject_id', $request->subject_id)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return response()->json(['error' => 'Teacher not assigned to this section and subject'], 403);
            }

            // Call the regular mark attendance method
            $request->merge(['teacher_id' => $teacherId]);
            return $this->markAttendance($request);

        } catch (\Exception $e) {
            Log::error('Error marking teacher attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark attendance'], 500);
        }
    }

    /**
     * Get attendance records for students (for calendar display)
     */
    public function getAttendanceRecords(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_ids' => 'required|string',
                'subject_id' => 'required|integer',
                'month' => 'required|integer|min:0|max:11',
                'year' => 'required|integer|min:2020|max:2030'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $studentIds = explode(',', $request->student_ids);
            $subjectId = $request->subject_id;
            $month = $request->month + 1; // Convert JS month (0-11) to PHP month (1-12)
            $year = $request->year;

            // Get attendance records from attendance_records table with sessions
            $records = DB::table('attendance_records as ar')
                ->join('attendance_sessions as as', 'ar.attendance_session_id', '=', 'as.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->whereIn('ar.student_id', $studentIds)
                ->where('as.subject_id', $subjectId)
                ->whereYear('as.session_date', $year)
                ->whereMonth('as.session_date', $month)
                ->where('ar.is_current_version', true)
                ->select([
                    'ar.student_id',
                    'as.session_date as date',
                    'ast.name as status',
                    'ast.code as status_code',
                    'ar.marked_at'
                ])
                ->get()
                ->map(function ($record) {
                    // Map status codes to full names
                    $statusMap = [
                        'P' => 'PRESENT',
                        'A' => 'ABSENT', 
                        'L' => 'LATE'
                    ];
                    
                    return [
                        'student_id' => $record->student_id,
                        'date' => $record->date,
                        'status' => $statusMap[$record->status_code] ?? 'UNKNOWN',
                        'status_name' => $record->status,
                        'status_code' => $record->status_code,
                        'marked_at' => $record->marked_at
                    ];
                });

            return response()->json([
                'success' => true,
                'records' => $records,
                'count' => $records->count(),
                'month' => $month,
                'year' => $year
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching attendance records: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance records',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
