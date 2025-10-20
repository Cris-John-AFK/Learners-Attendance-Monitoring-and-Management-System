<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ScheduleNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
 
class ScheduleNotificationController extends Controller
{
    protected $scheduleNotificationService;

    public function __construct(ScheduleNotificationService $scheduleNotificationService)
    {
        $this->scheduleNotificationService = $scheduleNotificationService;
    }

    /**
     * Get upcoming schedules for a teacher
     */
    public function getUpcomingSchedules($teacherId, Request $request)
    {
        try {
            $date = $request->query('date');
            $schedules = $this->scheduleNotificationService->getUpcomingSchedules($teacherId, $date);

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'count' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting upcoming schedules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching upcoming schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a schedule has an active session
     */
    public function getActiveSession($scheduleId, Request $request)
    {
        try {
            $date = $request->query('date');
            $session = $this->scheduleNotificationService->getActiveSession($scheduleId, $date);

            return response()->json([
                'success' => true,
                'has_session' => $session !== null,
                'session' => $session
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking active session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking active session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate session timing
     */
    public function validateSessionTiming(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'teacher_id' => 'required|exists:teachers,id',
                'section_id' => 'required|exists:sections,id',
                'subject_id' => 'required|exists:subjects,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validation = $this->scheduleNotificationService->validateSessionTiming(
                $request->teacher_id,
                $request->section_id,
                $request->subject_id
            );

            return response()->json([
                'success' => true,
                'validation' => $validation
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating session timing: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error validating session timing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process auto-absence marking for schedules
     */
    public function processAutoAbsence(Request $request)
    {
        try {
            $date = $request->query('date');
            $results = $this->scheduleNotificationService->processAutoAbsenceMarking($date);

            return response()->json([
                'success' => true,
                'results' => $results,
                'processed_count' => count($results)
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing auto-absence: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing auto-absence marking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get schedules that need auto-absence marking
     */
    public function getSchedulesNeedingAutoAbsence(Request $request)
    {
        try {
            $date = $request->query('date');
            $schedules = $this->scheduleNotificationService->getSchedulesNeedingAutoAbsence($date);

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'count' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting schedules needing auto-absence: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schedules needing auto-absence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark auto-absence for a specific session
     */
    public function markAutoAbsence($sessionId)
    {
        try {
            $result = $this->scheduleNotificationService->markAutoAbsence($sessionId);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully marked {$result['students_marked']} students as absent",
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark auto-absence',
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error marking auto-absence: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking auto-absence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher's current schedule status
     */
    public function getCurrentScheduleStatus($teacherId)
    {
        try {
            $schedules = $this->scheduleNotificationService->getUpcomingSchedules($teacherId);
            $now = now();
            
            // Find current active schedule
            $currentSchedule = $schedules->first(function ($schedule) use ($now) {
                return $schedule->status === 'ongoing';
            });

            // Find next upcoming schedule
            $nextSchedule = $schedules->first(function ($schedule) use ($now) {
                return $schedule->status === 'upcoming';
            });

            return response()->json([
                'success' => true,
                'current_schedule' => $currentSchedule,
                'next_schedule' => $nextSchedule,
                'has_active_schedule' => $currentSchedule !== null,
                'total_schedules_today' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting current schedule status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting schedule status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-create session and mark all students absent
     */
    public function autoCreateSession(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'schedule_id' => 'required',
                'teacher_id' => 'required|exists:teachers,id',
                'section_id' => 'required|exists:sections,id',
                'subject_id' => 'required|exists:subjects,id',
                'schedule_date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->scheduleNotificationService->autoCreateSessionAndMarkAbsent(
                $request->schedule_id,
                $request->teacher_id,
                $request->section_id,
                $request->subject_id,
                $request->schedule_date,
                $request->start_time,
                $request->end_time
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Session auto-created. {$result['marked_absent_count']} students marked absent",
                    'session_id' => $result['session_id'],
                    'marked_absent_count' => $result['marked_absent_count']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to auto-create session',
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error auto-creating session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error auto-creating session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
