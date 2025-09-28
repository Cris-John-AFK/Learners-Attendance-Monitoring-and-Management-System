<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentStatusChange;
use App\Models\StudentArchive;
use App\Models\Notification;
use App\Services\AttendanceAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminStudentManagementController extends Controller
{
    protected AttendanceAnalyticsService $analyticsService;

    public function __construct(AttendanceAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get all students with enhanced information for admin
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Student::query();

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('current_status', $request->status);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('firstName', 'ILIKE', "%{$search}%")
                      ->orWhere('lastName', 'ILIKE', "%{$search}%")
                      ->orWhere('studentId', 'ILIKE', "%{$search}%")
                      ->orWhere('lrn', 'ILIKE', "%{$search}%");
                });
            }

            if ($request->has('grade_level') && $request->grade_level) {
                $query->where('gradeLevel', $request->grade_level);
            }

            // Get students with relationships
            $students = $query->with([
                'sections' => function ($q) {
                    $q->wherePivot('is_active', true);
                }
            ])->orderBy('lastName')->orderBy('firstName')->get();

            // Enhance with analytics for active students
            $enhancedStudents = $students->map(function ($student) {
                if ($student->current_status === 'active') {
                    try {
                        $analytics = $this->analyticsService->generateStudentAnalytics($student->id);
                        $student->analytics = $analytics['analytics'];
                        $student->risk_level = $analytics['analytics']['risk_level'];
                        $student->attendance_percentage = $analytics['analytics']['attendance_percentage_last_30_days'];
                        $student->exceeds_18_limit = $analytics['analytics']['exceeds_18_absence_limit'];
                    } catch (\Exception $e) {
                        $student->analytics = null;
                        $student->risk_level = 'unknown';
                        $student->attendance_percentage = null;
                        $student->exceeds_18_limit = false;
                    }
                } else {
                    $student->analytics = null;
                    $student->risk_level = 'inactive';
                    $student->attendance_percentage = null;
                    $student->exceeds_18_limit = false;
                }

                return $student;
            });

            // Generate summary statistics
            $summary = [
                'total_students' => $enhancedStudents->count(),
                'active_students' => $enhancedStudents->where('current_status', 'active')->count(),
                'inactive_students' => $enhancedStudents->where('current_status', '!=', 'active')->count(),
                'critical_cases' => $enhancedStudents->where('exceeds_18_limit', true)->count(),
                'status_breakdown' => $enhancedStudents->groupBy('current_status')->map->count(),
                'risk_breakdown' => $enhancedStudents->where('current_status', 'active')->groupBy('risk_level')->map->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'students' => $enhancedStudents,
                    'summary' => $summary,
                    'available_statuses' => StudentStatusChange::getAvailableStatuses(),
                    'reason_categories' => StudentStatusChange::getReasonCategories()
                ],
                'message' => 'Students retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error retrieving students for admin: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve students',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Change student status (replaces delete functionality)
     * 
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function changeStatus(Request $request, int $studentId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_status' => 'required|string|in:' . implode(',', array_keys(StudentStatusChange::getAvailableStatuses())),
                'reason_category' => 'required|string|in:' . implode(',', array_keys(StudentStatusChange::getReasonCategories())),
                'reason_note' => 'nullable|string|max:500',
                'effective_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Mark previous status as not current
                StudentStatusChange::where('student_id', $studentId)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                // Create new status change record
                $statusChange = StudentStatusChange::create([
                    'student_id' => $studentId,
                    'changed_by_user_id' => Auth::id(),
                    'previous_status' => $student->current_status,
                    'new_status' => $request->new_status,
                    'reason_category' => $request->reason_category,
                    'reason_note' => $request->reason_note,
                    'effective_date' => $request->effective_date,
                    'is_current' => true,
                    'changed_at' => now()
                ]);

                // Update student's current status
                $student->update([
                    'current_status' => $request->new_status,
                    'status_changed_date' => $request->effective_date
                ]);

                // Auto-archive if status requires it
                if ($statusChange->requiresArchiving()) {
                    $this->scheduleAutoArchive($student, $statusChange);
                }

                // Create notifications for relevant users
                $this->createStatusChangeNotifications($student, $statusChange);

                DB::commit();

                Log::info("Student status changed by admin", [
                    'student_id' => $studentId,
                    'admin_id' => Auth::id(),
                    'old_status' => $statusChange->previous_status,
                    'new_status' => $statusChange->new_status
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student' => $student->fresh(),
                        'status_change' => $statusChange,
                        'will_be_archived' => $statusChange->requiresArchiving()
                    ],
                    'message' => 'Student status changed successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Error changing student status: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change student status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get student status history
     * 
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStatusHistory(int $studentId): JsonResponse
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            $statusHistory = StudentStatusChange::where('student_id', $studentId)
                ->with(['changedBy:id,name,email'])
                ->orderBy('changed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'student' => $student,
                    'status_history' => $statusHistory
                ],
                'message' => 'Status history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error retrieving status history: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve status history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get archived students
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getArchivedStudents(Request $request): JsonResponse
    {
        try {
            $query = StudentArchive::query();

            // Apply filters
            if ($request->has('final_status') && $request->final_status !== 'all') {
                $query->byFinalStatus($request->final_status);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereRaw("student_data->>'firstName' ILIKE ?", ["%{$search}%"])
                      ->orWhereRaw("student_data->>'lastName' ILIKE ?", ["%{$search}%"])
                      ->orWhereRaw("student_data->>'studentId' ILIKE ?", ["%{$search}%"]);
                });
            }

            if ($request->has('can_be_restored')) {
                $query->where('can_be_restored', $request->boolean('can_be_restored'));
            }

            $archivedStudents = $query->with(['archivedBy:id,name,email'])
                ->orderBy('archived_date', 'desc')
                ->get();

            // Generate summary
            $summary = [
                'total_archived' => $archivedStudents->count(),
                'restorable' => $archivedStudents->where('can_be_restored', true)->count(),
                'auto_archived' => $archivedStudents->whereNotNull('auto_archive_date')->count(),
                'status_breakdown' => $archivedStudents->groupBy('final_status')->map->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'archived_students' => $archivedStudents,
                    'summary' => $summary
                ],
                'message' => 'Archived students retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error retrieving archived students: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve archived students',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Restore student from archive (promotion system)
     * 
     * @param Request $request
     * @param int $archiveId
     * @return JsonResponse
     */
    public function restoreStudent(Request $request, int $archiveId): JsonResponse
    {
        try {
            $archive = StudentArchive::find($archiveId);
            if (!$archive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archive record not found'
                ], 404);
            }

            if (!$archive->can_be_restored) {
                return response()->json([
                    'success' => false,
                    'message' => 'This student cannot be restored'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Restore student
                $restoredStudent = $archive->restoreStudent();

                if (!$restoredStudent) {
                    throw new \Exception('Failed to restore student');
                }

                // Create notification
                Notification::create([
                    'user_id' => Auth::id(),
                    'type' => Notification::TYPE_SYSTEM,
                    'title' => 'Student Restored',
                    'message' => "Student {$restoredStudent->firstName} {$restoredStudent->lastName} has been restored from archive",
                    'data' => [
                        'student_id' => $restoredStudent->id,
                        'archive_id' => $archiveId,
                        'restored_by' => Auth::id()
                    ],
                    'priority' => Notification::PRIORITY_NORMAL
                ]);

                DB::commit();

                Log::info("Student restored from archive", [
                    'archive_id' => $archiveId,
                    'new_student_id' => $restoredStudent->id,
                    'restored_by' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'restored_student' => $restoredStudent,
                        'archive_record' => $archive->fresh()
                    ],
                    'message' => 'Student restored successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Error restoring student: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore student',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Manually archive a student
     * 
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function archiveStudent(Request $request, int $studentId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Check if student can be archived
            if ($student->current_status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot archive active student. Change status first.'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Create archive record
                $archive = StudentArchive::createFromStudent(
                    $student,
                    $student->current_status,
                    $request->reason,
                    Auth::id(),
                    false // Manual archive
                );

                DB::commit();

                Log::info("Student manually archived", [
                    'student_id' => $studentId,
                    'archive_id' => $archive->id,
                    'archived_by' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $archive,
                    'message' => 'Student archived successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Error archiving student: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive student',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Schedule auto-archive for students with certain statuses
     */
    private function scheduleAutoArchive(Student $student, StudentStatusChange $statusChange): void
    {
        // This would typically be handled by a job/queue system
        // For now, we'll just log that it should be scheduled
        Log::info("Auto-archive scheduled for student", [
            'student_id' => $student->id,
            'status' => $statusChange->new_status,
            'archive_date' => now()->addDays(30)->toDateString()
        ]);
    }

    /**
     * Create notifications for status changes
     */
    private function createStatusChangeNotifications(Student $student, StudentStatusChange $statusChange): void
    {
        // Notify relevant teachers
        $teachers = $student->sections()
            ->with('teacherAssignments.teacher.user')
            ->get()
            ->pluck('teacherAssignments')
            ->flatten()
            ->pluck('teacher.user')
            ->filter()
            ->unique('id');

        foreach ($teachers as $teacherUser) {
            Notification::createStatusChangeNotification(
                $teacherUser->id,
                $student->id,
                $statusChange->previous_status,
                $statusChange->new_status,
                Auth::id()
            );
        }

        // Notify other admins
        $adminUsers = \App\Models\User::where('role', 'admin')
            ->where('id', '!=', Auth::id())
            ->get();

        foreach ($adminUsers as $admin) {
            Notification::createStatusChangeNotification(
                $admin->id,
                $student->id,
                $statusChange->previous_status,
                $statusChange->new_status,
                Auth::id()
            );
        }
    }
}
