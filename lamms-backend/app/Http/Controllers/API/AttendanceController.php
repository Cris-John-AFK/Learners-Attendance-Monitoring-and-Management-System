<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\TeacherSectionSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    /**
     * Get attendance records for a specific date and subject
     */
    public function getAttendanceByDateAndSubject(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'subject_id' => 'required|integer',
            'section_id' => 'required|integer',
            'teacher_id' => 'required|integer'
        ]);

        try {
            // Verify teacher has access to this section/subject
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id
            ])->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section/subject'
                ], 403);
            }

            // Get students in the section
            $students = Student::where('section_id', $request->section_id)
                ->select('id', 'name', 'student_id', 'qr_code')
                ->get();

            // Get attendance records for the date
            $attendanceRecords = Attendance::where([
                'date' => $request->date,
                'subject_id' => $request->subject_id
            ])
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

            // Combine student data with attendance
            $attendanceData = $students->map(function ($student) use ($attendanceRecords) {
                $attendance = $attendanceRecords->get($student->id);
                
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'studentId' => $student->student_id,
                    'qrCode' => $student->qr_code,
                    'status' => $attendance ? $attendance->status : 'unmarked',
                    'timeIn' => $attendance ? $attendance->time_in : null,
                    'remarks' => $attendance ? $attendance->remarks : null,
                    'attendanceId' => $attendance ? $attendance->id : null
                ];
            });

            return response()->json([
                'students' => $attendanceData,
                'date' => $request->date,
                'subject_id' => $request->subject_id,
                'section_id' => $request->section_id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load attendance data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark attendance for a student
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'date' => 'required|date',
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'teacher_id' => 'required|integer|exists:teachers,id',
            'time_in' => 'nullable|date_format:H:i:s',
            'remarks' => 'nullable|string|max:255'
        ]);

        try {
            // Verify teacher has access
            $student = Student::findOrFail($request->student_id);
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $student->section_id,
                'subject_id' => $request->subject_id
            ])->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to mark attendance for this student'
                ], 403);
            }

            // Create or update attendance record
            $attendance = Attendance::updateOrCreate(
                [
                    'student_id' => $request->student_id,
                    'subject_id' => $request->subject_id,
                    'date' => $request->date
                ],
                [
                    'status' => $request->status,
                    'time_in' => $request->time_in ?: ($request->status === 'present' ? now()->format('H:i:s') : null),
                    'remarks' => $request->remarks,
                    'teacher_id' => $request->teacher_id
                ]
            );

            return response()->json([
                'message' => 'Attendance marked successfully',
                'attendance' => [
                    'id' => $attendance->id,
                    'student_id' => $attendance->student_id,
                    'status' => $attendance->status,
                    'time_in' => $attendance->time_in,
                    'remarks' => $attendance->remarks,
                    'date' => $attendance->date
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to mark attendance',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk mark attendance for multiple students
     */
    public function bulkMarkAttendance(Request $request)
    {
        $request->validate([
            'attendances' => 'required|array',
            'attendances.*.student_id' => 'required|integer|exists:students,id',
            'attendances.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'subject_id' => 'required|integer|exists:subjects,id',
            'date' => 'required|date',
            'teacher_id' => 'required|integer|exists:teachers,id'
        ]);

        try {
            $results = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($request->attendances as $attendanceData) {
                try {
                    // Verify teacher access for each student
                    $student = Student::findOrFail($attendanceData['student_id']);
                    $teacherAssignment = TeacherSectionSubject::where([
                        'teacher_id' => $request->teacher_id,
                        'section_id' => $student->section_id,
                        'subject_id' => $request->subject_id
                    ])->first();

                    if (!$teacherAssignment) {
                        $errors[] = "Unauthorized access for student ID: {$attendanceData['student_id']}";
                        continue;
                    }

                    $attendance = Attendance::updateOrCreate(
                        [
                            'student_id' => $attendanceData['student_id'],
                            'subject_id' => $request->subject_id,
                            'date' => $request->date
                        ],
                        [
                            'status' => $attendanceData['status'],
                            'time_in' => $attendanceData['time_in'] ?? ($attendanceData['status'] === 'present' ? now()->format('H:i:s') : null),
                            'remarks' => $attendanceData['remarks'] ?? null,
                            'teacher_id' => $request->teacher_id
                        ]
                    );

                    $results[] = [
                        'student_id' => $attendance->student_id,
                        'status' => $attendance->status,
                        'success' => true
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Failed to mark attendance for student ID {$attendanceData['student_id']}: " . $e->getMessage();
                }
            }

            if (empty($errors)) {
                DB::commit();
                return response()->json([
                    'message' => 'Bulk attendance marked successfully',
                    'results' => $results
                ]);
            } else {
                DB::rollback();
                return response()->json([
                    'error' => 'Some attendance records failed to save',
                    'errors' => $errors,
                    'successful' => $results
                ], 422);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Failed to process bulk attendance',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance history for a student
     */
    public function getStudentAttendanceHistory($studentId, Request $request)
    {
        $request->validate([
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $query = Attendance::with(['subject:id,name'])
                ->where('student_id', $studentId);

            if ($request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->start_date) {
                $query->where('date', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->where('date', '<=', $request->end_date);
            }

            $limit = $request->limit ?? 50;
            $attendanceHistory = $query->orderBy('date', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'student_id' => $studentId,
                'attendance_history' => $attendanceHistory->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'date' => $record->date,
                        'subject' => $record->subject->name ?? 'Unknown Subject',
                        'status' => $record->status,
                        'time_in' => $record->time_in,
                        'remarks' => $record->remarks
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load attendance history',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * QR Code attendance scanning
     */
    public function scanQRAttendance(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:teachers,id',
            'date' => 'nullable|date'
        ]);

        try {
            // Find student by QR code
            $student = Student::where('qr_code', $request->qr_code)->first();
            
            if (!$student) {
                return response()->json([
                    'error' => 'Invalid QR code - student not found'
                ], 404);
            }

            // Verify teacher has access to this student's section
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $student->section_id,
                'subject_id' => $request->subject_id
            ])->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized - teacher does not have access to this student\'s section'
                ], 403);
            }

            $date = $request->date ?? now()->format('Y-m-d');
            $currentTime = now()->format('H:i:s');

            // Check if attendance already marked for today
            $existingAttendance = Attendance::where([
                'student_id' => $student->id,
                'subject_id' => $request->subject_id,
                'date' => $date
            ])->first();

            if ($existingAttendance) {
                return response()->json([
                    'message' => 'Attendance already marked for this student today',
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'student_id' => $student->student_id
                    ],
                    'attendance' => [
                        'status' => $existingAttendance->status,
                        'time_in' => $existingAttendance->time_in,
                        'already_marked' => true
                    ]
                ]);
            }

            // Mark as present
            $attendance = Attendance::create([
                'student_id' => $student->id,
                'subject_id' => $request->subject_id,
                'date' => $date,
                'status' => 'present',
                'time_in' => $currentTime,
                'teacher_id' => $request->teacher_id,
                'remarks' => 'QR Code scan'
            ]);

            return response()->json([
                'message' => 'Attendance marked successfully via QR scan',
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'student_id' => $student->student_id
                ],
                'attendance' => [
                    'id' => $attendance->id,
                    'status' => $attendance->status,
                    'time_in' => $attendance->time_in,
                    'date' => $attendance->date
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process QR attendance',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance statistics for a teacher's classes
     */
    public function getAttendanceStats($teacherId, Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'subject_id' => 'nullable|integer|exists:subjects,id'
        ]);

        try {
            $startDate = $request->start_date ?? Carbon::now()->subDays(30)->format('Y-m-d');
            $endDate = $request->end_date ?? Carbon::now()->format('Y-m-d');

            $query = DB::table('teacher_section_subjects')
                ->join('sections', 'teacher_section_subjects.section_id', '=', 'sections.id')
                ->join('students', 'students.section_id', '=', 'sections.id')
                ->join('attendances', 'students.id', '=', 'attendances.student_id')
                ->where('teacher_section_subjects.teacher_id', $teacherId)
                ->whereBetween('attendances.date', [$startDate, $endDate]);

            if ($request->subject_id) {
                $query->where('teacher_section_subjects.subject_id', $request->subject_id);
            }

            $stats = $query->select(
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count'),
                DB::raw('SUM(CASE WHEN attendances.status = "absent" THEN 1 ELSE 0 END) as absent_count'),
                DB::raw('SUM(CASE WHEN attendances.status = "late" THEN 1 ELSE 0 END) as late_count'),
                DB::raw('SUM(CASE WHEN attendances.status = "excused" THEN 1 ELSE 0 END) as excused_count')
            )->first();

            $totalRecords = $stats->total_records ?? 0;
            $attendanceRate = $totalRecords > 0 ? 
                round(($stats->present_count / $totalRecords) * 100, 2) : 0;

            return response()->json([
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'statistics' => [
                    'total_records' => $totalRecords,
                    'present_count' => $stats->present_count ?? 0,
                    'absent_count' => $stats->absent_count ?? 0,
                    'late_count' => $stats->late_count ?? 0,
                    'excused_count' => $stats->excused_count ?? 0,
                    'attendance_rate' => $attendanceRate
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load attendance statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
