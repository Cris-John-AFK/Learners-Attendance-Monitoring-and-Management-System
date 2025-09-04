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
        $validator = Validator::make($request->all(), [
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
            $sectionId = $request->section_id;
            $subjectId = $request->subject_id;
            $teacherId = $request->teacher_id;
            $date = $request->date;
            $attendanceData = $request->attendance;

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
            $assignment = \DB::table('teacher_section_subject')
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
}
