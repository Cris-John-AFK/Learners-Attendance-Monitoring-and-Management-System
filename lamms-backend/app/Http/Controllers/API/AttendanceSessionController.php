<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceStatus;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AttendanceSessionController extends Controller
{
    /**
     * Create a new attendance session
     */
    public function createSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'session_date' => 'required|date',
            'session_start_time' => 'required|date_format:H:i:s',
            'session_type' => 'nullable|in:regular,makeup,special',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if there's already an active session for this combination
            $existingSession = AttendanceSession::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'session_date' => $request->session_date,
                'status' => 'active'
            ])->first();

            if ($existingSession) {
                return response()->json([
                    'message' => 'Active session already exists',
                    'session' => $existingSession->load(['teacher', 'section', 'subject'])
                ], 200);
            }

            $session = AttendanceSession::create([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'session_date' => $request->session_date,
                'session_start_time' => $request->session_start_time,
                'session_type' => $request->session_type ?? 'regular',
                'status' => 'active',
                'metadata' => $request->metadata ?? []
            ]);

            return response()->json([
                'message' => 'Attendance session created successfully',
                'session' => $session->load(['teacher', 'section', 'subject'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating attendance session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create attendance session'], 500);
        }
    }

    /**
     * Get active sessions for a teacher
     */
    public function getActiveSessionsForTeacher($teacherId)
    {
        try {
            $sessions = AttendanceSession::with(['section', 'subject', 'attendanceRecords.student', 'attendanceRecords.attendanceStatus'])
                ->where('teacher_id', $teacherId)
                ->active()
                ->forDate(now()->toDateString())
                ->get();

            return response()->json([
                'sessions' => $sessions
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching active sessions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch active sessions'], 500);
        }
    }

    /**
     * Mark attendance for a session
     */
    public function markSessionAttendance(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:student_details,id',
            'attendance.*.attendance_status_id' => 'required|exists:attendance_statuses,id',
            'attendance.*.arrival_time' => 'nullable|date_format:H:i:s',
            'attendance.*.remarks' => 'nullable|string|max:500',
            'attendance.*.marking_method' => 'nullable|in:manual,qr_scan,auto,bulk'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $session = AttendanceSession::findOrFail($sessionId);
            
            if (!$session->isActive()) {
                return response()->json(['error' => 'Session is not active'], 400);
            }

            $savedRecords = [];

            DB::transaction(function () use ($request, $session, &$savedRecords) {
                foreach ($request->attendance as $attendanceData) {
                    $record = AttendanceRecord::updateOrCreate(
                        [
                            'attendance_session_id' => $session->id,
                            'student_id' => $attendanceData['student_id']
                        ],
                        [
                            'attendance_status_id' => $attendanceData['attendance_status_id'],
                            'marked_by_teacher_id' => $session->teacher_id,
                            'marked_at' => now(),
                            'arrival_time' => $attendanceData['arrival_time'] ?? null,
                            'remarks' => $attendanceData['remarks'] ?? null,
                            'marking_method' => $attendanceData['marking_method'] ?? 'manual',
                            'marked_from_ip' => request()->ip()
                        ]
                    );

                    $savedRecords[] = $record->load(['student', 'attendanceStatus']);
                }
            });

            return response()->json([
                'message' => 'Attendance marked successfully',
                'session' => $session,
                'records' => $savedRecords
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking session attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark attendance'], 500);
        }
    }

    /**
     * Mark single student attendance via QR scan
     */
    public function markQRAttendance(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:student_details,id',
            'attendance_status_id' => 'nullable|exists:attendance_statuses,id',
            'arrival_time' => 'nullable|date_format:H:i:s',
            'location_data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $session = AttendanceSession::findOrFail($sessionId);
            
            if (!$session->isActive()) {
                return response()->json(['error' => 'Session is not active'], 400);
            }

            // Check if student is already marked
            $existingRecord = AttendanceRecord::where([
                'attendance_session_id' => $session->id,
                'student_id' => $request->student_id
            ])->first();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'Student already marked',
                    'record' => $existingRecord->load(['student', 'attendanceStatus'])
                ], 200);
            }

            // Auto-determine status based on arrival time if not provided
            $statusId = $request->attendance_status_id;
            if (!$statusId) {
                $arrivalTime = $request->arrival_time ? Carbon::createFromFormat('H:i:s', $request->arrival_time) : now();
                $sessionStart = Carbon::createFromFormat('H:i:s', $session->session_start_time);
                
                // Default logic: Present if on time, Late if 15+ minutes late
                if ($arrivalTime->diffInMinutes($sessionStart, false) > 15) {
                    $statusId = AttendanceStatus::where('code', 'L')->first()->id; // Late
                } else {
                    $statusId = AttendanceStatus::where('code', 'P')->first()->id; // Present
                }
            }

            $record = AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'student_id' => $request->student_id,
                'attendance_status_id' => $statusId,
                'marked_by_teacher_id' => $session->teacher_id,
                'marked_at' => now(),
                'arrival_time' => $request->arrival_time ?? now()->format('H:i:s'),
                'marking_method' => 'qr_scan',
                'marked_from_ip' => request()->ip(),
                'location_data' => $request->location_data
            ]);

            return response()->json([
                'message' => 'QR attendance marked successfully',
                'record' => $record->load(['student', 'attendanceStatus'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking QR attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark QR attendance'], 500);
        }
    }

    /**
     * Complete an attendance session
     */
    public function completeSession($sessionId)
    {
        try {
            $session = AttendanceSession::findOrFail($sessionId);
            
            if ($session->status !== 'active') {
                return response()->json(['error' => 'Session is not active'], 400);
            }

            $session->update([
                'status' => 'completed',
                'session_end_time' => now()->format('H:i:s'),
                'completed_at' => now()
            ]);

            return response()->json([
                'message' => 'Session completed successfully',
                'session' => $session->load(['attendanceRecords.student', 'attendanceRecords.attendanceStatus'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to complete session'], 500);
        }
    }

    /**
     * Get session attendance summary
     */
    public function getSessionSummary($sessionId)
    {
        try {
            $session = AttendanceSession::with([
                'section',
                'subject',
                'teacher',
                'attendanceRecords.student',
                'attendanceRecords.attendanceStatus'
            ])->findOrFail($sessionId);

            // Get all students in the section
            $allStudents = Student::inSection($session->section_id)->active()->get();
            
            // Get attendance records
            $records = $session->attendanceRecords;
            $markedStudentIds = $records->pluck('student_id')->toArray();
            
            // Calculate statistics
            $stats = [
                'total_students' => $allStudents->count(),
                'marked_students' => $records->count(),
                'unmarked_students' => $allStudents->count() - $records->count(),
                'present' => $records->whereIn('attendanceStatus.code', ['P'])->count(),
                'absent' => $records->whereIn('attendanceStatus.code', ['A'])->count(),
                'late' => $records->whereIn('attendanceStatus.code', ['L'])->count(),
                'excused' => $records->whereIn('attendanceStatus.code', ['E'])->count()
            ];

            // Get unmarked students
            $unmarkedStudents = $allStudents->whereNotIn('id', $markedStudentIds)->values();

            return response()->json([
                'session' => $session,
                'statistics' => $stats,
                'attendance_records' => $records,
                'unmarked_students' => $unmarkedStudents
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting session summary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get session summary'], 500);
        }
    }

    /**
     * Get weekly attendance report
     */
    public function getWeeklyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'week_start' => 'required|date',
            'subject_id' => 'nullable|exists:subjects,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $weekStart = Carbon::parse($request->week_start)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $query = AttendanceSession::with([
                'attendanceRecords.student',
                'attendanceRecords.attendanceStatus',
                'subject'
            ])
            ->where('section_id', $request->section_id)
            ->whereBetween('session_date', [$weekStart, $weekEnd]);

            if ($request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            $sessions = $query->get();

            // Group by student and calculate weekly stats
            $studentStats = [];
            foreach ($sessions as $session) {
                foreach ($session->attendanceRecords as $record) {
                    $studentId = $record->student_id;
                    if (!isset($studentStats[$studentId])) {
                        $studentStats[$studentId] = [
                            'student' => $record->student,
                            'days' => [],
                            'summary' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0]
                        ];
                    }

                    $day = $session->session_date;
                    $status = $record->attendanceStatus->code;
                    
                    $studentStats[$studentId]['days'][$day] = [
                        'status' => $status,
                        'status_name' => $record->attendanceStatus->name,
                        'subject' => $session->subject->name ?? 'General',
                        'arrival_time' => $record->arrival_time
                    ];

                    // Update summary
                    switch ($status) {
                        case 'P': $studentStats[$studentId]['summary']['present']++; break;
                        case 'A': $studentStats[$studentId]['summary']['absent']++; break;
                        case 'L': $studentStats[$studentId]['summary']['late']++; break;
                        case 'E': $studentStats[$studentId]['summary']['excused']++; break;
                    }
                }
            }

            return response()->json([
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'student_attendance' => array_values($studentStats)
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating weekly report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate weekly report'], 500);
        }
    }

    /**
     * Get monthly attendance report
     */
    public function getMonthlyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'month' => 'required|date_format:Y-m',
            'subject_id' => 'nullable|exists:subjects,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $monthStart = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $query = AttendanceSession::with([
                'attendanceRecords.student',
                'attendanceRecords.attendanceStatus',
                'subject'
            ])
            ->where('section_id', $request->section_id)
            ->whereBetween('session_date', [$monthStart, $monthEnd]);

            if ($request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            $sessions = $query->get();

            // Calculate monthly statistics
            $studentStats = [];
            $totalSchoolDays = $sessions->groupBy('session_date')->count();

            foreach ($sessions as $session) {
                foreach ($session->attendanceRecords as $record) {
                    $studentId = $record->student_id;
                    if (!isset($studentStats[$studentId])) {
                        $studentStats[$studentId] = [
                            'student' => $record->student,
                            'total_days' => 0,
                            'present' => 0,
                            'absent' => 0,
                            'late' => 0,
                            'excused' => 0,
                            'attendance_rate' => 0
                        ];
                    }

                    $status = $record->attendanceStatus->code;
                    $studentStats[$studentId]['total_days']++;
                    
                    switch ($status) {
                        case 'P': $studentStats[$studentId]['present']++; break;
                        case 'A': $studentStats[$studentId]['absent']++; break;
                        case 'L': $studentStats[$studentId]['late']++; break;
                        case 'E': $studentStats[$studentId]['excused']++; break;
                    }
                }
            }

            // Calculate attendance rates
            foreach ($studentStats as &$stats) {
                $attendedDays = $stats['present'] + $stats['late'];
                $stats['attendance_rate'] = $stats['total_days'] > 0 ? 
                    round(($attendedDays / $stats['total_days']) * 100, 2) : 0;
            }

            return response()->json([
                'month' => $request->month,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'total_school_days' => $totalSchoolDays,
                'student_attendance' => array_values($studentStats)
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating monthly report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate monthly report'], 500);
        }
    }
}
