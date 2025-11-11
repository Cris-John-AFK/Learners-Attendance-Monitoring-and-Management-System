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
        // Handle subject_id conversion from string to integer if needed
        $requestData = $request->all();
        if (isset($requestData['subject_id']) && !is_numeric($requestData['subject_id'])) {
            $subject = \App\Models\Subject::where('name', 'ILIKE', $requestData['subject_id'])
                ->orWhere('code', 'ILIKE', $requestData['subject_id'])
                ->first();

            if ($subject) {
                $requestData['subject_id'] = $subject->id;
                Log::info('Converted subject identifier to ID in attendance session', [
                    'original' => $request->input('subject_id'),
                    'resolved_id' => $subject->id,
                    'subject_name' => $subject->name
                ]);
            } else {
                Log::error('Subject not found for attendance session', [
                    'subject_identifier' => $requestData['subject_id']
                ]);
                return response()->json([
                    'message' => 'Subject not found',
                    'error' => 'Invalid subject identifier: ' . $requestData['subject_id']
                ], 422);
            }
        }

        $validator = Validator::make($requestData, [
            'teacher_id' => 'required|exists:teachers,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'session_date' => 'required|date',
            'session_type' => 'nullable|in:regular,makeup,special',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if there's already an active session for this combination
            $existingSession = AttendanceSession::where([
                'teacher_id' => $requestData['teacher_id'],
                'section_id' => $requestData['section_id'],
                'subject_id' => $requestData['subject_id'],
                'session_date' => $requestData['session_date'],
                'status' => 'active'
            ])->first();

            if ($existingSession) {
                Log::info("Active session already exists for teacher {$requestData['teacher_id']}, section {$requestData['section_id']}, subject {$requestData['subject_id']}, date {$requestData['session_date']}");
                return response()->json([
                    'message' => 'Active session already exists',
                    'session' => $existingSession->load(['teacher', 'section', 'subject'])
                ], 200);
            }

            // Allow multiple sessions per day, but log for tracking
            $completedSessionsCount = AttendanceSession::where([
                'teacher_id' => $requestData['teacher_id'],
                'section_id' => $requestData['section_id'],
                'subject_id' => $requestData['subject_id'],
                'session_date' => $requestData['session_date'],
                'status' => 'completed'
            ])->count();

            if ($completedSessionsCount > 0) {
                Log::info("Creating additional session - {$completedSessionsCount} completed session(s) already exist for teacher {$requestData['teacher_id']}, section {$requestData['section_id']}, subject {$requestData['subject_id']}, date {$requestData['session_date']}");
            }

            // Create the session - always use current time as start time
            $session = AttendanceSession::create([
                'teacher_id' => $requestData['teacher_id'],
                'section_id' => $requestData['section_id'],
                'subject_id' => $requestData['subject_id'],
                'session_date' => $requestData['session_date'],
                'session_start_time' => now()->format('H:i:s'), // Use current time
                'session_type' => $requestData['session_type'] ?? 'regular',
                'status' => 'active',
                'metadata' => $requestData['metadata'] ?? []
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
            $sessions = AttendanceSession::with([
                'section',
                'subject',
                'attendanceRecords.student',
                'attendanceRecords.attendanceStatus',
                'attendanceRecords.attendanceReason'
            ])
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
            'attendance.*.marking_method' => 'nullable|in:manual,qr_scan,auto,bulk',
            'attendance.*.reason_id' => 'nullable|exists:attendance_reasons,id',
            'attendance.*.reason_notes' => 'nullable|string|max:500'
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
                    Log::info('💾 Saving attendance record', [
                        'student_id' => $attendanceData['student_id'],
                        'status_id' => $attendanceData['attendance_status_id'],
                        'reason_id' => $attendanceData['reason_id'] ?? null,
                        'reason_notes' => $attendanceData['reason_notes'] ?? null
                    ]);

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
                            'marked_from_ip' => request()->ip(),
                            'reason_id' => $attendanceData['reason_id'] ?? null,
                            'reason_notes' => $attendanceData['reason_notes'] ?? null
                        ]
                    );

                    Log::info('✅ Saved record', [
                        'record_id' => $record->id,
                        'reason_id_saved' => $record->reason_id,
                        'reason_notes_saved' => $record->reason_notes
                    ]);

                    $savedRecords[] = $record->load(['student', 'attendanceStatus', 'attendanceReason']);
                }
            });

            return response()->json([
                'message' => 'Attendance marked successfully',
                'session' => $session,
                'records' => $savedRecords
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking session attendance: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'error' => 'Failed to mark attendance',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
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
            $session = AttendanceSession::with('section')->findOrFail($sessionId);

            Log::info("Attempting to complete session {$sessionId}. Current status: {$session->status}");

            // Check if session is already completed
            if ($session->status === 'completed') {
                Log::info("Session {$sessionId} already completed");
                return response()->json([
                    'message' => 'Session already completed',
                    'summary' => $this->generateSessionSummary($session)
                ]);
            }

            if ($session->status !== 'active') {
                Log::warning("Session {$sessionId} is not active. Status: {$session->status}");
                return response()->json(['error' => 'Session is not active'], 400);
            }

            // Use database transaction for data integrity and prevent duplicate completions
            $autoMarkedCount = 0;
            DB::transaction(function () use ($session, &$autoMarkedCount) {
                // Double-check status within transaction to prevent race conditions
                $currentSession = AttendanceSession::lockForUpdate()->find($session->id);

                if ($currentSession->status !== 'active') {
                    throw new \Exception("Session status changed during completion. Current status: {$currentSession->status}");
                }

                // Auto-mark unmarked students as absent before completing
                $autoMarkedCount = $this->autoMarkUnmarkedStudentsAsAbsent($currentSession);

                // Update session with completion data
                $currentSession->update([
                    'status' => 'completed',
                    'session_end_time' => now()->format('H:i:s'),
                    'completed_at' => now()
                ]);

                Log::info("Session {$session->id} completed successfully with transaction lock. Auto-marked {$autoMarkedCount} students as absent.");
            });

            // Refresh session to get updated data
            $session = $session->fresh();

            // Generate session summary for the modal
            $summary = $this->generateSessionSummary($session);

            // Create notification for session completion (async, non-blocking)
            Log::info("About to create notification for session {$session->id}");
            try {
                $this->createSessionCompletionNotification($session, $summary);
                Log::info("Notification creation completed for session {$session->id}");
            } catch (\Exception $notifError) {
                Log::error("Failed to create notification: " . $notifError->getMessage());
                // Don't fail the session completion if notification fails
            }

            return response()->json([
                'message' => 'Session completed successfully',
                'summary' => $summary
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Database error completing session {$sessionId}: " . $e->getMessage());
            Log::error("SQL Error Code: " . $e->getCode());
            Log::error("SQL Error Info: " . json_encode($e->errorInfo ?? []));

            // Handle specific constraint violations
            if (str_contains($e->getMessage(), 'unique_active_session')) {
                return response()->json([
                    'error' => 'Cannot complete session due to unique constraint violation',
                    'message' => 'Another session with the same parameters already exists',
                    'details' => config('app.debug') ? $e->getMessage() : 'Duplicate session detected'
                ], 409); // Conflict status code
            }

            return response()->json([
                'error' => 'Database error occurred while completing session',
                'details' => config('app.debug') ? $e->getMessage() : 'Please check server logs'
            ], 500);

        } catch (\Exception $e) {
            Log::error("General error completing session {$sessionId}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            // Handle session status change during completion
            if (str_contains($e->getMessage(), 'Session status changed during completion')) {
                return response()->json([
                    'error' => 'Session was modified by another process',
                    'message' => 'Please refresh and try again',
                    'details' => $e->getMessage()
                ], 409); // Conflict status code
            }

            return response()->json([
                'error' => 'Failed to complete session',
                'details' => config('app.debug') ? $e->getMessage() : 'Please check server logs'
            ], 500);
        }
    }

    /**
     * Generate session summary for completion modal
     */
    private function generateSessionSummary($session)
    {
        $session->load(['section', 'subject', 'teacher', 'attendanceRecords.student', 'attendanceRecords.attendanceStatus']);

        // Get all ENROLLED students in the section for accurate counts (exclude dropped-out)
        $totalStudents = DB::table('student_details as sd')
            ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
            ->where('ss.section_id', $session->section_id)
            ->where(function($query) {
                $query->where('ss.is_active', true)
                      ->orWhereNull('ss.is_active');
            })
            // CRITICAL: Only count enrolled students, exclude dropped-out students
            ->where(function($query) {
                $query->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                      ->orWhereNull('sd.enrollment_status');
            })
            ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
            ->count();

        // Filter attendance records to exclude dropped-out students
        $allRecords = $session->attendanceRecords;
        $records = $allRecords->filter(function($record) {
            $student = $record->student;
            if (!$student) return false;
            
            // Exclude dropped-out students from display
            $excludedStatuses = ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'];
            return !in_array($student->enrollment_status, $excludedStatuses);
        });

        // Calculate attendance statistics ONLY for enrolled students
        $stats = [
            'total_students' => $totalStudents,
            'marked_students' => $records->count(),
            'present' => $records->filter(fn($r) => $r->attendanceStatus->code === 'P')->count(),
            'absent' => $records->filter(fn($r) => $r->attendanceStatus->code === 'A')->count(),
            'late' => $records->filter(fn($r) => $r->attendanceStatus->code === 'L')->count(),
            'excused' => $records->filter(fn($r) => $r->attendanceStatus->code === 'E')->count()
        ];

        return [
            'session_id' => $session->id,
            'session_date' => $session->session_date,
            'session_start_time' => $session->session_start_time,
            'session_end_time' => $session->session_end_time,
            'subject_name' => $session->subject->name ?? 'Homeroom',
            'section_name' => $session->section->name,
            'teacher_name' => $session->teacher->first_name . ' ' . $session->teacher->last_name,
            'statistics' => $stats,
            'attendance_records' => $records->map(function ($record) {
                return [
                    'student_id' => $record->student_id,
                    'student_name' => $record->student->name,
                    'status' => $record->attendanceStatus->name,
                    'status_code' => $record->attendanceStatus->code,
                    'arrival_time' => $record->arrival_time,
                    'remarks' => $record->remarks
                ];
            })
        ];
    }


    /**
     * Clean up existing sessions by removing dropped-out student records
     */
    public function cleanupExistingSession($sessionId)
    {
        try {
            // Get attendance records for dropped-out students in this session
            $droppedOutRecords = DB::table('attendance_records as ar')
                ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
                ->where('ar.attendance_session_id', $sessionId)
                ->whereIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
                ->select('ar.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
                ->get();
            
            if ($droppedOutRecords->count() > 0) {
                // Delete the attendance records
                $recordIds = $droppedOutRecords->pluck('id')->toArray();
                DB::table('attendance_records')->whereIn('id', $recordIds)->delete();
                
                Log::info("🧹 Cleaned up existing session - removed dropped-out students", [
                    'session_id' => $sessionId,
                    'removed_records' => $droppedOutRecords->count(),
                    'students' => $droppedOutRecords->map(function($record) {
                        return $record->firstName . ' ' . $record->lastName . ' (' . $record->enrollment_status . ')';
                    })->toArray()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Cleaned up session - removed dropped-out students',
                    'removed_count' => $droppedOutRecords->count()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'No dropped-out students found in this session',
                'removed_count' => 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error cleaning up existing session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to cleanup session'], 500);
        }
    }

    /**
     * Auto-cleanup dropped-out students from session when viewing summary
     */
    private function autoCleanupDroppedOutStudents($sessionId)
    {
        try {
            // Get attendance records for dropped-out students in this session
            $droppedOutRecords = DB::table('attendance_records as ar')
                ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
                ->where('ar.attendance_session_id', $sessionId)
                ->whereIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
                ->select('ar.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status')
                ->get();
            
            if ($droppedOutRecords->count() > 0) {
                // Delete the attendance records
                $recordIds = $droppedOutRecords->pluck('id')->toArray();
                DB::table('attendance_records')->whereIn('id', $recordIds)->delete();
                
                Log::info("🧹 AUTO-CLEANUP: Removed dropped-out students from session", [
                    'session_id' => $sessionId,
                    'removed_records' => $droppedOutRecords->count(),
                    'students' => $droppedOutRecords->map(function($record) {
                        return $record->firstName . ' ' . $record->lastName . ' (' . $record->enrollment_status . ')';
                    })->toArray()
                ]);
                
                return $droppedOutRecords->count();
            }
            
            return 0;
            
        } catch (\Exception $e) {
            Log::error('Error in auto-cleanup: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up ALL sessions by removing dropped-out students (for immediate deployment)
     */
    public function cleanupAllSessions()
    {
        try {
            // Get all attendance records for dropped-out students
            $droppedOutRecords = DB::table('attendance_records as ar')
                ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->whereIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
                ->select('ar.id', 'sd.firstName', 'sd.lastName', 'sd.enrollment_status', 'ases.id as session_id')
                ->get();
            
            if ($droppedOutRecords->count() > 0) {
                // Delete all the attendance records
                $recordIds = $droppedOutRecords->pluck('id')->toArray();
                DB::table('attendance_records')->whereIn('id', $recordIds)->delete();
                
                // Group by session for logging
                $sessionGroups = $droppedOutRecords->groupBy('session_id');
                
                Log::info("🧹 GLOBAL CLEANUP: Removed dropped-out students from ALL sessions", [
                    'total_removed_records' => $droppedOutRecords->count(),
                    'affected_sessions' => $sessionGroups->count(),
                    'students_removed' => $droppedOutRecords->map(function($record) {
                        return $record->firstName . ' ' . $record->lastName . ' (' . $record->enrollment_status . ')';
                    })->unique()->values()->toArray()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully cleaned up all sessions',
                    'removed_records' => $droppedOutRecords->count(),
                    'affected_sessions' => $sessionGroups->count()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'No dropped-out students found in any sessions',
                'removed_records' => 0,
                'affected_sessions' => 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in global cleanup: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to cleanup all sessions'], 500);
        }
    }

    /**
     * Get session attendance summary
     */
    public function getSessionSummary($sessionId)
    {
        try {
            // DON'T delete records - just filter them out in the display
            
            $session = AttendanceSession::with([
                'section',
                'subject',
                'teacher',
                'attendanceRecords.student',
                'attendanceRecords.attendanceStatus'
            ])->findOrFail($sessionId);

            // Get all ACTIVE students in the section (exclude dropped-out students)
            $allStudents = DB::table('student_details as sd')
                ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                ->where('ss.section_id', $session->section_id)
                ->where(function($query) {
                    $query->where('ss.is_active', true)
                          ->orWhereNull('ss.is_active');
                })
                // CRITICAL: Only count active students in statistics
                ->where(function($query) {
                    $query->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                          ->orWhereNull('sd.enrollment_status');
                })
                ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
                ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.middleName')
                ->get();

            // Get attendance records and FILTER OUT dropped-out students
            $allRecords = $session->attendanceRecords;
            
            // Filter records to exclude dropped-out students
            $records = $allRecords->filter(function($record) {
                $student = $record->student;
                if (!$student) return false;
                
                // Exclude dropped-out students from display
                $excludedStatuses = ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'];
                return !in_array($student->enrollment_status, $excludedStatuses);
            });
            
            $markedStudentIds = $records->pluck('student_id')->toArray();

            // Calculate statistics ONLY for active students
            $stats = [
                'total_students' => $allStudents->count(),
                'marked_students' => $records->count(),
                'unmarked_students' => $allStudents->count() - $records->count(),
                'present' => $records->filter(fn($r) => $r->attendanceStatus->code === 'P')->count(),
                'absent' => $records->filter(fn($r) => $r->attendanceStatus->code === 'A')->count(),
                'late' => $records->filter(fn($r) => $r->attendanceStatus->code === 'L')->count(),
                'excused' => $records->filter(fn($r) => $r->attendanceStatus->code === 'E')->count()
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
                'attendanceRecords' => function($query) {
                    $query->where('is_current_version', true);
                },
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

            // Return available dates for the date picker
            $availableDates = $sessions->pluck('session_date')
                ->map(function($date) {
                    return $date->format('Y-m-d');
                })
                ->unique()
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'available_dates' => $availableDates,
                'sessions_count' => $sessions->count()
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

    /**
     * Edit a completed attendance session (creates new version)
     */
    public function editSession(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'edit_reason' => 'required|in:correction,late_entry,system_error,administrative',
            'edit_notes' => 'required|string|max:1000',
            'session_data' => 'nullable|array',
            'attendance_records' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $originalSession = AttendanceSession::findOrFail($sessionId);

            // Only allow editing of completed sessions
            if ($originalSession->status !== 'completed') {
                return response()->json(['error' => 'Only completed sessions can be edited'], 400);
            }

            DB::transaction(function () use ($request, $originalSession) {
                // Mark original session as not current
                $originalSession->update(['is_current_version' => false]);

                // Create new version of the session
                $newSession = $originalSession->replicate();
                $newSession->version = $originalSession->version + 1;
                $newSession->original_session_id = $originalSession->original_session_id ?? $originalSession->id;
                $newSession->edit_reason = $request->edit_reason;
                $newSession->edit_notes = $request->edit_notes;
                $newSession->edited_by_teacher_id = $originalSession->teacher_id; // TODO: Get from auth
                $newSession->edited_at = now();
                $newSession->is_current_version = true;

                // Apply session data changes if provided
                if ($request->session_data) {
                    foreach ($request->session_data as $field => $value) {
                        if (in_array($field, ['session_start_time', 'session_end_time', 'session_type', 'metadata'])) {
                            $newSession->$field = $value;
                        }
                    }
                }

                $newSession->save();

                // Log the edit in audit trail
                $this->logAuditEvent('session', $newSession->id, 'edit', $originalSession->teacher_id, [
                    'original_session_id' => $originalSession->id,
                    'version' => $newSession->version,
                    'edit_reason' => $request->edit_reason,
                    'edit_notes' => $request->edit_notes
                ]);

                // Handle attendance records editing if provided
                if ($request->attendance_records) {
                    $this->editAttendanceRecords($newSession, $request->attendance_records, $request->edit_reason);
                }

                // Update session statistics
                $this->updateSessionStatistics($newSession);
            });

            return response()->json([
                'message' => 'Session edited successfully',
                'session' => $originalSession->fresh()->load(['teacher', 'section', 'subject'])
            ]);

        } catch (\Exception $e) {
            Log::error("Error editing session {$sessionId}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to edit session'], 500);
        }
    }

    /**
     * Get session edit history
     */
    public function getSessionHistory($sessionId)
    {
        try {
            $session = AttendanceSession::findOrFail($sessionId);
            $originalId = $session->original_session_id ?? $session->id;

            // Get all versions of this session
            $versions = AttendanceSession::where(function($query) use ($originalId, $session) {
                $query->where('id', $originalId)
                      ->orWhere('original_session_id', $originalId);
            })
            ->with(['teacher', 'editedByTeacher'])
            ->orderBy('version')
            ->get();

            // Get detailed edit history
            $editHistory = DB::table('attendance_session_edits')
                ->where('session_id', $originalId)
                ->orWhereIn('session_id', $versions->pluck('id'))
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'original_session_id' => $originalId,
                'current_version' => $versions->where('is_current_version', true)->first(),
                'all_versions' => $versions,
                'edit_history' => $editHistory
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting session history for {$sessionId}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to get session history'], 500);
        }
    }

    /**
     * Edit attendance records for a session
     */
    private function editAttendanceRecords($session, $recordsData, $editReason)
    {
        foreach ($recordsData as $recordData) {
            if (isset($recordData['id'])) {
                // Editing existing record
                $originalRecord = AttendanceRecord::find($recordData['id']);
                if ($originalRecord) {
                    // Mark original as not current
                    $originalRecord->update(['is_current_version' => false]);

                    // Create new version
                    $newRecord = $originalRecord->replicate();
                    $newRecord->version = $originalRecord->version + 1;
                    $newRecord->original_record_id = $originalRecord->original_record_id ?? $originalRecord->id;
                    $newRecord->attendance_session_id = $session->id;
                    $newRecord->is_current_version = true;

                    // Apply changes
                    if (isset($recordData['attendance_status_id'])) {
                        $newRecord->attendance_status_id = $recordData['attendance_status_id'];
                    }
                    if (isset($recordData['arrival_time'])) {
                        $newRecord->arrival_time = $recordData['arrival_time'];
                    }
                    if (isset($recordData['remarks'])) {
                        $newRecord->remarks = $recordData['remarks'];
                    }

                    $newRecord->save();

                    // Log the change
                    $this->logAuditEvent('record', $newRecord->id, 'edit', $session->teacher_id, [
                        'original_record_id' => $originalRecord->id,
                        'edit_reason' => $editReason
                    ]);
                }
            } else {
                // Adding new record
                AttendanceRecord::create([
                    'attendance_session_id' => $session->id,
                    'student_id' => $recordData['student_id'],
                    'attendance_status_id' => $recordData['attendance_status_id'],
                    'marked_by_teacher_id' => $session->teacher_id,
                    'marked_at' => now(),
                    'arrival_time' => $recordData['arrival_time'] ?? null,
                    'remarks' => $recordData['remarks'] ?? null,
                    'marking_method' => 'manual',
                    'data_source' => 'manual',
                    'version' => 1,
                    'is_current_version' => true
                ]);
            }
        }
    }

    /**
     * Update session statistics cache
     */
    private function updateSessionStatistics($session)
    {
        $records = $session->attendanceRecords()->where('is_current_version', true)->with('attendanceStatus')->get();
        $allStudents = Student::inSection($session->section_id)->active()->count();

        $stats = [
            'total_students' => $allStudents,
            'marked_students' => $records->count(),
            'present_count' => $records->where('attendanceStatus.code', 'P')->count(),
            'absent_count' => $records->where('attendanceStatus.code', 'A')->count(),
            'late_count' => $records->where('attendanceStatus.code', 'L')->count(),
            'excused_count' => $records->where('attendanceStatus.code', 'E')->count(),
        ];

        $stats['attendance_rate'] = $stats['total_students'] > 0 ?
            (($stats['present_count'] + $stats['late_count']) / $stats['total_students']) * 100 : 0;

        // Update or create statistics record
        DB::table('attendance_session_stats')->updateOrInsert(
            ['session_id' => $session->id],
            array_merge($stats, [
                'detailed_stats' => json_encode($records->groupBy('attendanceStatus.code')),
                'calculated_at' => now(),
                'updated_at' => now()
            ])
        );
    }

    /**
     * Get teacher's attendance sessions
     */
    public function getTeacherAttendanceSessions($teacherId)
    {
        try {
            // Get the sections that this teacher is assigned to
            $assignedSectionIds = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacherId)
                ->where('is_active', true)
                ->pluck('section_id')
                ->unique()
                ->toArray();

            // Get sessions for the teacher's assigned sections only
            $sessions = AttendanceSession::with(['section', 'subject'])
                ->whereIn('section_id', $assignedSectionIds)
                ->orderBy('session_date', 'desc')
                ->orderBy('session_start_time', 'desc')
                ->get()
                ->map(function ($session) {
                    // Get attendance counts
                    $attendanceRecords = AttendanceRecord::where('attendance_session_id', $session->id)->get();
                    $statusCounts = $attendanceRecords->groupBy('attendance_status_id')->map->count();

                    return [
                        'id' => $session->id,
                        'session_date' => $session->session_date,
                        'start_time' => $session->session_start_time ? \Carbon\Carbon::parse($session->session_start_time)->format('H:i:s') : null,
                        'end_time' => $session->session_end_time ? \Carbon\Carbon::parse($session->session_end_time)->format('H:i:s') : null,
                        'subject_name' => $session->subject->name ?? 'Unknown Subject',
                        'section_name' => $session->section->name ?? 'Unknown Section',
                        'total_students' => $attendanceRecords->count(),
                        'present_count' => $statusCounts->get(1, 0), // Present status ID = 1
                        'absent_count' => $statusCounts->get(2, 0),  // Absent status ID = 2
                        'late_count' => $statusCounts->get(3, 0),    // Late status ID = 3
                        'excused_count' => $statusCounts->get(4, 0), // Excused status ID = 4
                    ];
                });

            return response()->json([
                'success' => true,
                'sessions' => $sessions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching teacher attendance sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session attendance details with students
     */
    public function getSessionAttendanceDetails($sessionId)
    {
        try {
            $session = AttendanceSession::with(['section', 'subject'])->findOrFail($sessionId);

            $attendanceRecords = AttendanceRecord::with(['student', 'attendanceStatus', 'attendanceReason'])
                ->where('attendance_session_id', $sessionId)
                ->get();

            $students = $attendanceRecords->map(function ($record) {
                return [
                    'id' => $record->student->id,
                    'student_id' => $record->student->student_id,
                    'name' => $record->student->firstName . ' ' . $record->student->lastName,
                    'status' => $record->attendanceStatus->name ?? 'Present',
                    'reason_id' => $record->reason_id,
                    'reason_notes' => $record->reason_notes,
                    'attendance_reason' => $record->attendanceReason ? [
                        'id' => $record->attendanceReason->id,
                        'reason_name' => $record->attendanceReason->reason_name,
                        'status' => $record->attendanceReason->status
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'session_date' => $session->session_date,
                    'start_time' => $session->session_start_time ? \Carbon\Carbon::parse($session->session_start_time)->format('H:i:s') : null,
                    'end_time' => $session->session_end_time ? \Carbon\Carbon::parse($session->session_end_time)->format('H:i:s') : null,
                    'subject_name' => $session->subject->name ?? 'Unknown Subject',
                    'section_name' => $session->section->name ?? 'Unknown Section',
                ],
                'students' => $students
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching session attendance details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update student attendance status in a session
     */
    public function updateStudentAttendance(Request $request, $sessionId, $studentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:Present,Absent,Late,Excused',
                'reason_id' => 'nullable|exists:attendance_reasons,id',
                'reason_notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the attendance status ID
            $statusMap = [
                'Present' => 1,
                'Absent' => 2,
                'Late' => 3,
                'Excused' => 4
            ];

            $statusId = $statusMap[$request->status];

            // Update the attendance record
            $attendanceRecord = AttendanceRecord::where('attendance_session_id', $sessionId)
                ->where('student_id', $studentId)
                ->first();

            if ($attendanceRecord) {
                $attendanceRecord->update([
                    'attendance_status_id' => $statusId,
                    'reason_id' => $request->reason_id ?? null,
                    'reason_notes' => $request->reason_notes ?? null,
                    'updated_at' => now()
                ]);
            } else {
                // Create new attendance record if it doesn't exist
                AttendanceRecord::create([
                    'attendance_session_id' => $sessionId,
                    'student_id' => $studentId,
                    'attendance_status_id' => $statusId,
                    'reason_id' => $request->reason_id ?? null,
                    'reason_notes' => $request->reason_notes ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            Log::info('✅ Updated attendance record', [
                'student_id' => $studentId,
                'status' => $request->status,
                'reason_id' => $request->reason_id,
                'reason_notes' => $request->reason_notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating student attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-mark absent students when schedule ends
     */
    public function autoMarkAbsent(Request $request, $sessionId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'schedule_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'section_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the session
            $session = AttendanceSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance session not found'
                ], 404);
            }

            // Get all students in the section
            $students = Student::where('section_id', $request->section_id)
                ->where('is_active', true)
                ->get();

            // Get students who already have attendance records for this session
            $existingRecords = AttendanceRecord::where('session_id', $sessionId)
                ->pluck('student_id')
                ->toArray();

            // Find students without attendance records
            $unmarkedStudents = $students->whereNotIn('id', $existingRecords);

            $markedCount = 0;
            $absentStatusId = AttendanceStatus::where('name', 'Absent')->first()->id;

            // Mark unmarked students as absent
            foreach ($unmarkedStudents as $student) {
                AttendanceRecord::create([
                    'session_id' => $sessionId,
                    'student_id' => $student->id,
                    'status_id' => $absentStatusId,
                    'recorded_at' => now(),
                    'recorded_by_teacher_id' => $session->teacher_id,
                    'method' => 'Auto-marked (Schedule End)',
                    'notes' => 'Automatically marked absent when schedule ended'
                ]);

                // Log audit event
                $this->logAuditEvent(
                    'attendance_record',
                    $student->id,
                    'auto_mark_absent',
                    $session->teacher_id,
                    [
                        'session_id' => $sessionId,
                        'schedule_id' => $request->schedule_id,
                        'student_name' => $student->first_name . ' ' . $student->last_name,
                        'reason' => 'Schedule ended, student not marked'
                    ]
                );

                $markedCount++;
            }

            // Update session status to completed if not already
            if ($session->status !== 'completed') {
                $session->update([
                    'status' => 'completed',
                    'ended_at' => now()
                ]);
            }

            Log::info('Auto-marked absent students', [
                'session_id' => $sessionId,
                'schedule_id' => $request->schedule_id,
                'marked_count' => $markedCount,
                'total_students' => $students->count(),
                'existing_records' => count($existingRecords)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Students automatically marked absent',
                'marked_absent_count' => $markedCount,
                'total_students' => $students->count(),
                'already_marked' => count($existingRecords)
            ]);

        } catch (\Exception $e) {
            Log::error('Error auto-marking absent students: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-mark absent students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create notification when session is completed
     * Optimized for performance - runs after session completion
     */
    private function createSessionCompletionNotification($session, $summary)
    {
        try {
            // Extract statistics from summary (they're nested under 'statistics' key)
            $stats = $summary['statistics'] ?? [];
            $subjectName = $summary['subject_name'] ?? 'Unknown Subject';
            
            // Get section name from the session relationship
            $sectionName = 'Unknown Section';
            if ($session->section) {
                $sectionName = $session->section->name;
            }

            // Create compact notification message with section info
            $presentCount = $stats['present'] ?? 0;
            $absentCount = $stats['absent'] ?? 0;
            $lateCount = $stats['late'] ?? 0;

            $message = "{$subjectName} - {$sectionName} - {$presentCount} present, {$absentCount} absent";

            // Insert notification using indexed user_id column
            DB::table('notifications')->insert([
                'type' => 'session_completed',
                'user_id' => $session->teacher_id, // Using user_id which has index
                'title' => 'Attendance Session Completed',
                'message' => $message,
                'data' => json_encode([
                    'session_id' => $session->id,
                    'subject_id' => $session->subject_id,
                    'subject_name' => $subjectName,
                    'section_id' => $session->section_id,
                    'section_name' => $sectionName,
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                    'late_count' => $lateCount,
                    'excused_count' => $stats['excused'] ?? 0,
                    'total_students' => $stats['total_students'] ?? 0,
                    'teacher_id' => $session->teacher_id
                ]),
                'priority' => 'medium',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info("Notification created for completed session {$session->id} - {$message}");
        } catch (\Exception $e) {
            // Don't fail session completion if notification fails
            Log::error("Failed to create notification for session {$session->id}: " . $e->getMessage());
            Log::error("Summary structure: " . json_encode($summary));
        }
    }

    /**
     * Auto-mark unmarked students as absent when completing session
     */
    private function autoMarkUnmarkedStudentsAsAbsent($session)
    {
        try {
            // Get all ENROLLED students in the section (exclude dropped-out students)
            $allStudents = DB::table('student_section as ss')
                ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
                ->where('ss.section_id', $session->section_id)
                ->where('ss.is_active', true)
                ->where('sd.is_active', true)
                // CRITICAL: Only include enrolled students, exclude dropped-out students
                ->where(function($query) {
                    $query->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                          ->orWhereNull('sd.enrollment_status');
                })
                ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
                ->pluck('ss.student_id');

            // Get students who already have attendance
            $markedStudents = DB::table('attendance_records')
                ->where('attendance_session_id', $session->id)
                ->pluck('student_id');

            // Find unmarked students
            $unmarkedStudents = $allStudents->diff($markedStudents);

            if ($unmarkedStudents->isEmpty()) {
                Log::info("No unmarked students for session {$session->id}");
                return 0;
            }

            // Get Absent status ID
            $absentStatus = DB::table('attendance_statuses')
                ->where('code', 'A')
                ->where('is_active', true)
                ->first();

            if (!$absentStatus) {
                Log::error("Absent status not found in database");
                return 0;
            }

            // Create attendance records for unmarked students
            $records = [];
            foreach ($unmarkedStudents as $studentId) {
                $records[] = [
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                    'attendance_status_id' => $absentStatus->id,
                    'marked_by_teacher_id' => $session->teacher_id,
                    'marked_at' => now(),
                    'remarks' => 'Auto-marked absent when session completed',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            DB::table('attendance_records')->insert($records);

            Log::info("Auto-marked {$unmarkedStudents->count()} students as absent for session {$session->id}");
            return $unmarkedStudents->count();

        } catch (\Exception $e) {
            Log::error("Error auto-marking absent students for session {$session->id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get the most recent completed session for a section
     * Used for auto-filling attendance from previous session
     * Now fetches from ANY date (not just today) to get the most recent completed session
     */
    public function getMostRecentSessionToday(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'date' => 'nullable|date' // Made optional since we're getting most recent from any date
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sectionId = $request->section_id;

            // Get the most recent COMPLETED session from ANY date (not just today)
            $session = AttendanceSession::where('section_id', $sectionId)
                ->where('status', 'completed') // Only completed sessions
                ->orderBy('session_date', 'desc') // Most recent date first
                ->orderBy('session_end_time', 'desc') // Then by end time
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No completed session found for this section'
                ], 404);
            }

            // Get attendance records for this session with student details
            $attendanceRecords = AttendanceRecord::where('attendance_session_id', $session->id)
                ->join('attendance_statuses', 'attendance_records.attendance_status_id', '=', 'attendance_statuses.id')
                ->select(
                    'attendance_records.*',
                    'attendance_statuses.code as status_code',
                    'attendance_statuses.name as status'
                )
                ->get();

            Log::info('Auto-fill: Found most recent session', [
                'session_id' => $session->id,
                'session_date' => $session->session_date,
                'section_id' => $sectionId,
                'attendance_count' => $attendanceRecords->count()
            ]);

            return response()->json([
                'success' => true,
                'session' => $session,
                'attendance_records' => $attendanceRecords,
                'message' => 'Most recent session retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching most recent session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve most recent session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log audit events
     */
    private function logAuditEvent($entityType, $entityId, $action, $teacherId, $context = [])
    {
        DB::table('attendance_audit_log')->insert([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'performed_by_teacher_id' => $teacherId,
            'context' => json_encode($context),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
