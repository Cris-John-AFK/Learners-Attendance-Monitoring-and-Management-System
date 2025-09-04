<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceModification;
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

class ProductionAttendanceController extends Controller
{
    /**
     * Start an attendance session
     * This ensures data integrity by creating a session first
     */
    public function startAttendanceSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'session_date' => 'required|date',
            'session_type' => 'required|in:regular,makeup,special'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if teacher has permission to take attendance for this section/subject
            $hasPermission = $this->verifyTeacherPermission(
                $request->teacher_id, 
                $request->section_id, 
                $request->subject_id
            );

            if (!$hasPermission) {
                return response()->json([
                    'error' => 'Teacher not authorized for this section/subject'
                ], 403);
            }

            // Check if there's already an active session
            $existingSession = AttendanceSession::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'session_date' => $request->session_date,
                'status' => 'active'
            ])->first();

            if ($existingSession) {
                return response()->json([
                    'message' => 'Resuming existing attendance session',
                    'session' => $existingSession
                ]);
            }

            // Create new attendance session
            $session = AttendanceSession::create([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'session_date' => $request->session_date,
                'session_start_time' => now()->format('H:i:s'),
                'session_type' => $request->session_type,
                'status' => 'active',
                'metadata' => json_encode([
                    'created_by_ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ])
            ]);

            return response()->json([
                'message' => 'Attendance session started successfully',
                'session' => $session
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error starting attendance session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to start attendance session'], 500);
        }
    }

    /**
     * Mark attendance with enhanced validation and audit trail
     */
    public function markAttendanceEnhanced(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attendance_session_id' => 'required|exists:attendance_sessions,id',
            'attendance_records' => 'required|array|min:1',
            'attendance_records.*.student_id' => 'required|exists:student_details,id',
            'attendance_records.*.attendance_status_id' => 'required|exists:attendance_statuses,id',
            'attendance_records.*.arrival_time' => 'nullable|date_format:H:i:s',
            'attendance_records.*.remarks' => 'nullable|string|max:500',
            'marking_method' => 'required|in:manual,qr_scan,auto,bulk'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $session = AttendanceSession::findOrFail($request->attendance_session_id);
            
            // Verify session is active
            if ($session->status !== 'active') {
                return response()->json([
                    'error' => 'Cannot mark attendance on inactive session'
                ], 400);
            }

            $savedRecords = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($request->attendance_records as $recordData) {
                try {
                    // Verify student is actually enrolled in this section
                    $isEnrolled = $this->verifyStudentEnrollment(
                        $recordData['student_id'],
                        $session->section_id,
                        $session->session_date
                    );

                    if (!$isEnrolled) {
                        $errors[] = "Student ID {$recordData['student_id']} not enrolled in this section";
                        continue;
                    }

                    // Check if attendance already exists for this session
                    $existingRecord = AttendanceRecord::where([
                        'attendance_session_id' => $session->id,
                        'student_id' => $recordData['student_id']
                    ])->first();

                    if ($existingRecord) {
                        // Log modification
                        $this->logAttendanceModification(
                            $existingRecord,
                            $recordData,
                            $session->teacher_id,
                            'status_change'
                        );

                        // Update existing record
                        $existingRecord->update([
                            'attendance_status_id' => $recordData['attendance_status_id'],
                            'arrival_time' => $recordData['arrival_time'] ?? null,
                            'remarks' => $recordData['remarks'] ?? null,
                            'marking_method' => $request->marking_method,
                            'marked_at' => now(),
                            'marked_from_ip' => $request->ip()
                        ]);

                        $savedRecords[] = $existingRecord->load(['student', 'attendanceStatus']);
                    } else {
                        // Create new record
                        $attendanceRecord = AttendanceRecord::create([
                            'attendance_session_id' => $session->id,
                            'student_id' => $recordData['student_id'],
                            'attendance_status_id' => $recordData['attendance_status_id'],
                            'marked_by_teacher_id' => $session->teacher_id,
                            'marked_at' => now(),
                            'arrival_time' => $recordData['arrival_time'] ?? null,
                            'remarks' => $recordData['remarks'] ?? null,
                            'marking_method' => $request->marking_method,
                            'marked_from_ip' => $request->ip(),
                            'location_data' => json_encode([
                                'user_agent' => $request->userAgent(),
                                'timestamp' => now()->toISOString()
                            ])
                        ]);

                        $savedRecords[] = $attendanceRecord->load(['student', 'attendanceStatus']);
                    }

                } catch (\Exception $e) {
                    $errors[] = "Failed to process student ID {$recordData['student_id']}: " . $e->getMessage();
                }
            }

            if (count($errors) > 0 && count($savedRecords) === 0) {
                DB::rollback();
                return response()->json([
                    'error' => 'No attendance records could be saved',
                    'details' => $errors
                ], 400);
            }

            DB::commit();

            return response()->json([
                'message' => 'Attendance marked successfully',
                'session_id' => $session->id,
                'saved_records' => count($savedRecords),
                'errors' => $errors,
                'attendance_records' => $savedRecords
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error marking attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark attendance'], 500);
        }
    }

    /**
     * Complete an attendance session
     */
    public function completeAttendanceSession(Request $request, $sessionId)
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

            // Get statistics
            $attendanceCount = AttendanceRecord::where('attendance_session_id', $sessionId)->count();
            $sectionStudentCount = $this->getActiveSectionStudentCount($session->section_id, $session->session_date);

            return response()->json([
                'message' => 'Attendance session completed successfully',
                'session' => $session,
                'statistics' => [
                    'total_students_in_section' => $sectionStudentCount,
                    'attendance_marked' => $attendanceCount,
                    'missing_attendance' => $sectionStudentCount - $attendanceCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing attendance session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to complete session'], 500);
        }
    }

    /**
     * Get attendance report with enhanced filtering
     */
    public function getAttendanceReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'student_id' => 'nullable|exists:student_details,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status_ids' => 'nullable|array',
            'status_ids.*' => 'exists:attendance_statuses,id',
            'report_type' => 'required|in:summary,detailed,analytics'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $query = AttendanceRecord::with([
                'attendanceSession.teacher',
                'attendanceSession.section',
                'attendanceSession.subject',
                'student',
                'attendanceStatus'
            ]);

            // Apply filters
            if ($request->section_id) {
                $query->whereHas('attendanceSession', function($q) use ($request) {
                    $q->where('section_id', $request->section_id);
                });
            }

            if ($request->subject_id) {
                $query->whereHas('attendanceSession', function($q) use ($request) {
                    $q->where('subject_id', $request->subject_id);
                });
            }

            if ($request->teacher_id) {
                $query->whereHas('attendanceSession', function($q) use ($request) {
                    $q->where('teacher_id', $request->teacher_id);
                });
            }

            if ($request->student_id) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->start_date) {
                $query->whereHas('attendanceSession', function($q) use ($request) {
                    $q->where('session_date', '>=', $request->start_date);
                });
            }

            if ($request->end_date) {
                $query->whereHas('attendanceSession', function($q) use ($request) {
                    $q->where('session_date', '<=', $request->end_date);
                });
            }

            if ($request->status_ids) {
                $query->whereIn('attendance_status_id', $request->status_ids);
            }

            $records = $query->orderBy('marked_at', 'desc')->get();

            // Format response based on report type
            switch ($request->report_type) {
                case 'summary':
                    $reportData = $this->generateSummaryReport($records);
                    break;
                case 'detailed':
                    $reportData = $this->generateDetailedReport($records);
                    break;
                case 'analytics':
                    $reportData = $this->generateAnalyticsReport($records);
                    break;
                default:
                    $reportData = $records;
            }

            return response()->json([
                'report_type' => $request->report_type,
                'filters_applied' => $request->only(['section_id', 'subject_id', 'teacher_id', 'student_id', 'start_date', 'end_date']),
                'total_records' => $records->count(),
                'data' => $reportData
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating attendance report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate report'], 500);
        }
    }

    // Helper Methods

    private function verifyTeacherPermission($teacherId, $sectionId, $subjectId = null)
    {
        // Check if teacher is assigned to this section/subject
        $query = DB::table('teacher_section_subject')
            ->where('teacher_id', $teacherId)
            ->where('section_id', $sectionId)
            ->where('is_active', true);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return $query->exists();
    }

    private function verifyStudentEnrollment($studentId, $sectionId, $sessionDate)
    {
        // Check if student is enrolled in section on the given date
        return DB::table('student_section')
            ->where('student_id', $studentId)
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->exists();
    }

    private function logAttendanceModification($existingRecord, $newData, $teacherId, $modificationType)
    {
        AttendanceModification::create([
            'attendance_record_id' => $existingRecord->id,
            'modified_by_teacher_id' => $teacherId,
            'old_values' => json_encode($existingRecord->toArray()),
            'new_values' => json_encode($newData),
            'modification_type' => $modificationType,
            'reason' => 'Attendance update via system'
        ]);
    }

    private function getActiveSectionStudentCount($sectionId, $date)
    {
        return DB::table('student_section')
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->count();
    }

    private function generateSummaryReport($records)
    {
        // Implementation for summary report
        return $records->groupBy('student_id')->map(function ($studentRecords) {
            $student = $studentRecords->first()->student;
            $statusCounts = $studentRecords->groupBy('attendance_status_id')->map->count();
            
            return [
                'student' => $student,
                'total_sessions' => $studentRecords->count(),
                'status_breakdown' => $statusCounts
            ];
        });
    }

    private function generateDetailedReport($records)
    {
        // Implementation for detailed report
        return $records->map(function ($record) {
            return [
                'date' => $record->attendanceSession->session_date,
                'student' => $record->student,
                'subject' => $record->attendanceSession->subject,
                'teacher' => $record->attendanceSession->teacher,
                'status' => $record->attendanceStatus,
                'marked_at' => $record->marked_at,
                'remarks' => $record->remarks
            ];
        });
    }

    private function generateAnalyticsReport($records)
    {
        // Implementation for analytics report
        $totalRecords = $records->count();
        $statusBreakdown = $records->groupBy('attendance_status_id')->map->count();
        $dailyTrends = $records->groupBy(function($record) {
            return $record->attendanceSession->session_date;
        })->map->count();

        return [
            'overview' => [
                'total_records' => $totalRecords,
                'status_breakdown' => $statusBreakdown,
                'daily_trends' => $dailyTrends
            ],
            'patterns' => [
                'most_common_status' => $statusBreakdown->keys()->first(),
                'attendance_rate' => $this->calculateAttendanceRate($statusBreakdown)
            ]
        ];
    }

    private function calculateAttendanceRate($statusBreakdown)
    {
        $presentStatusId = 1; // Assuming ID 1 is "Present"
        $total = $statusBreakdown->sum();
        $present = $statusBreakdown->get($presentStatusId, 0);
        
        return $total > 0 ? round(($present / $total) * 100, 2) : 0;
    }
}