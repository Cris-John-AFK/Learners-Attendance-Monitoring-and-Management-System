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
            DB::transaction(function () use ($session) {
                // Double-check status within transaction to prevent race conditions
                $currentSession = AttendanceSession::lockForUpdate()->find($session->id);
                
                if ($currentSession->status !== 'active') {
                    throw new \Exception("Session status changed during completion. Current status: {$currentSession->status}");
                }
                
                // Update session with completion data
                $currentSession->update([
                    'status' => 'completed',
                    'session_end_time' => now()->format('H:i:s'),
                    'completed_at' => now()
                ]);
                
                Log::info("Session {$session->id} completed successfully with transaction lock");
            });

            // Generate session summary for the modal
            $summary = $this->generateSessionSummary($session->fresh());

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
        
        // Get all students in the section for accurate counts
        $allStudents = Student::inSection($session->section_id)->active()->get();
        $records = $session->attendanceRecords;
        
        // Calculate attendance statistics
        $stats = [
            'total_students' => $allStudents->count(),
            'marked_students' => $records->count(),
            'present' => $records->where('attendanceStatus.code', 'P')->count(),
            'absent' => $records->where('attendanceStatus.code', 'A')->count(),
            'late' => $records->where('attendanceStatus.code', 'L')->count(),
            'excused' => $records->where('attendanceStatus.code', 'E')->count()
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
            $sessions = AttendanceSession::with(['section', 'subject'])
                ->where('teacher_id', $teacherId)
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
            
            $attendanceRecords = AttendanceRecord::with(['student', 'attendanceStatus'])
                ->where('attendance_session_id', $sessionId)
                ->get();

            $students = $attendanceRecords->map(function ($record) {
                return [
                    'id' => $record->student->id,
                    'student_id' => $record->student->student_id,
                    'name' => $record->student->firstName . ' ' . $record->student->lastName,
                    'status' => $record->attendanceStatus->name ?? 'Present'
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
                'status' => 'required|string|in:Present,Absent,Late,Excused'
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
                    'updated_at' => now()
                ]);
            } else {
                // Create new attendance record if it doesn't exist
                AttendanceRecord::create([
                    'attendance_session_id' => $sessionId,
                    'student_id' => $studentId,
                    'attendance_status_id' => $statusId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

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
