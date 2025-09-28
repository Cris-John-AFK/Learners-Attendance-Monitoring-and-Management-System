<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AttendanceAnalyticsService;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\TeacherSectionSubject;
use App\Models\StudentStatusChange;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TeacherStudentManagementController extends Controller
{
    protected AttendanceAnalyticsService $analyticsService;

    public function __construct(AttendanceAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get all students assigned to teacher with three-view support
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudents(Request $request): JsonResponse
    {
        try {
            $teacherId = $this->getTeacherId($request);
            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found'
                ], 404);
            }

            $view = $request->input('view', 'all'); // all, by_subject, by_section
            $filters = $this->parseFilters($request);

            Log::info("Getting students for teacher {$teacherId} with view: {$view}");

            switch ($view) {
                case 'by_subject':
                    $data = $this->getStudentsBySubject($teacherId, $filters);
                    break;
                case 'by_section':
                    $data = $this->getStudentsBySection($teacherId, $filters);
                    break;
                default:
                    $data = $this->getAllStudents($teacherId, $filters);
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'view' => $view,
                'filters_applied' => $filters,
                'message' => 'Students retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error retrieving students: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve students',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all students assigned to teacher
     */
    private function getAllStudents(int $teacherId, array $filters): array
    {
        // Get all unique students assigned to this teacher
        $studentsQuery = Student::whereHas('sections', function ($query) use ($teacherId) {
            $query->whereHas('teacherAssignments', function ($tq) use ($teacherId) {
                $tq->where('teacher_id', $teacherId)
                  ->where('is_active', true);
            });
        })->where('current_status', 'active');

        // Apply filters
        $studentsQuery = $this->applyFilters($studentsQuery, $filters);

        $students = $studentsQuery->with([
            'sections' => function ($query) use ($teacherId) {
                $query->whereHas('teacherAssignments', function ($tq) use ($teacherId) {
                    $tq->where('teacher_id', $teacherId)->where('is_active', true);
                });
            }
        ])->get();

        // Enhance with analytics data
        $enhancedStudents = $this->enhanceStudentsWithAnalytics($students);

        return [
            'students' => $enhancedStudents,
            'total_count' => $enhancedStudents->count(),
            'summary' => $this->generateStudentsSummary($enhancedStudents)
        ];
    }

    /**
     * Get students grouped by subject
     */
    private function getStudentsBySubject(int $teacherId, array $filters): array
    {
        $assignments = TeacherSectionSubject::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->with(['section', 'subject'])
            ->get();

        $subjectGroups = [];

        foreach ($assignments as $assignment) {
            $subjectName = $assignment->subject ? $assignment->subject->name : 'Homeroom';
            $subjectId = $assignment->subject_id ?? 'homeroom';

            if (!isset($subjectGroups[$subjectId])) {
                $subjectGroups[$subjectId] = [
                    'subject_name' => $subjectName,
                    'subject_id' => $subjectId,
                    'sections' => [],
                    'students' => collect(),
                    'total_students' => 0
                ];
            }

            // Get students in this section
            $studentsQuery = Student::whereHas('sections', function ($query) use ($assignment) {
                $query->where('sections.id', $assignment->section_id);
            })->where('current_status', 'active');

            // Apply filters
            $studentsQuery = $this->applyFilters($studentsQuery, $filters);
            $students = $studentsQuery->get();

            $subjectGroups[$subjectId]['sections'][] = [
                'section_id' => $assignment->section_id,
                'section_name' => $assignment->section->name,
                'student_count' => $students->count()
            ];

            $subjectGroups[$subjectId]['students'] = $subjectGroups[$subjectId]['students']->merge($students);
        }

        // Enhance each group with analytics
        foreach ($subjectGroups as $subjectId => &$group) {
            $group['students'] = $this->enhanceStudentsWithAnalytics($group['students']->unique('id'));
            $group['total_students'] = $group['students']->count();
            $group['summary'] = $this->generateStudentsSummary($group['students']);
        }

        return [
            'subjects' => array_values($subjectGroups),
            'total_subjects' => count($subjectGroups),
            'overall_summary' => $this->generateOverallSummary($subjectGroups)
        ];
    }

    /**
     * Get students grouped by section
     */
    private function getStudentsBySection(int $teacherId, array $filters): array
    {
        $assignments = TeacherSectionSubject::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->with(['section'])
            ->get()
            ->unique('section_id');

        $sectionGroups = [];

        foreach ($assignments as $assignment) {
            $sectionId = $assignment->section_id;
            $sectionName = $assignment->section->name;

            // Get students in this section
            $studentsQuery = Student::whereHas('sections', function ($query) use ($sectionId) {
                $query->where('sections.id', $sectionId);
            })->where('current_status', 'active');

            // Apply filters
            $studentsQuery = $this->applyFilters($studentsQuery, $filters);
            $students = $studentsQuery->get();

            // Get teacher's subjects for this section
            $subjects = TeacherSectionSubject::where('teacher_id', $teacherId)
                ->where('section_id', $sectionId)
                ->where('is_active', true)
                ->with('subject')
                ->get();

            $sectionGroups[] = [
                'section_id' => $sectionId,
                'section_name' => $sectionName,
                'grade_level' => $assignment->section->grade_level ?? 'Unknown',
                'subjects' => $subjects->map(function ($subj) {
                    return [
                        'subject_id' => $subj->subject_id,
                        'subject_name' => $subj->subject ? $subj->subject->name : 'Homeroom',
                        'role' => $subj->role
                    ];
                }),
                'students' => $this->enhanceStudentsWithAnalytics($students),
                'total_students' => $students->count(),
                'summary' => $this->generateStudentsSummary($this->enhanceStudentsWithAnalytics($students))
            ];
        }

        return [
            'sections' => $sectionGroups,
            'total_sections' => count($sectionGroups),
            'overall_summary' => $this->generateOverallSummary($sectionGroups)
        ];
    }

    /**
     * Change student status (dropout, transfer, etc.)
     * 
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function changeStudentStatus(Request $request, int $studentId): JsonResponse
    {
        try {
            $teacherId = $this->getTeacherId($request);
            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found'
                ], 404);
            }

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

            // Verify student belongs to teacher
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            $belongsToTeacher = $this->verifyStudentBelongsToTeacher($teacherId, $studentId);
            if (!$belongsToTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not assigned to this teacher'
                ], 403);
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

                // Create notification for admin
                $this->createStatusChangeNotification($student, $statusChange);

                DB::commit();

                Log::info("Student status changed successfully", [
                    'student_id' => $studentId,
                    'teacher_id' => $teacherId,
                    'old_status' => $statusChange->previous_status,
                    'new_status' => $statusChange->new_status
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student' => $student,
                        'status_change' => $statusChange
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
     * Get available status options and reasons
     * 
     * @return JsonResponse
     */
    public function getStatusOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => StudentStatusChange::getAvailableStatuses(),
                'reason_categories' => StudentStatusChange::getReasonCategories()
            ],
            'message' => 'Status options retrieved successfully'
        ]);
    }

    /**
     * Parse filters from request
     */
    private function parseFilters(Request $request): array
    {
        return [
            'attendance_percentage_min' => $request->input('attendance_min'),
            'attendance_percentage_max' => $request->input('attendance_max'),
            'risk_level' => $request->input('risk_level'),
            'exceeds_18_limit' => $request->boolean('exceeds_18_limit'),
            'recent_absences_days' => $request->input('recent_absences_days', 7),
            'recent_absences_min' => $request->input('recent_absences_min'),
            'search' => $request->input('search')
        ];
    }

    /**
     * Apply filters to students query
     */
    private function applyFilters($query, array $filters)
    {
        // Search by name
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'ILIKE', "%{$search}%")
                  ->orWhere('lastName', 'ILIKE', "%{$search}%")
                  ->orWhere('studentId', 'ILIKE', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Enhance students with analytics data
     */
    private function enhanceStudentsWithAnalytics($students)
    {
        return $students->map(function ($student) {
            try {
                $analytics = $this->analyticsService->generateStudentAnalytics($student->id);
                $student->analytics = $analytics['analytics'];
                $student->recommendations = $analytics['recommendations'];
                $student->risk_color = $this->getRiskColor($analytics['analytics']['risk_level']);
            } catch (\Exception $e) {
                // If analytics fail, provide default values
                $student->analytics = [
                    'risk_level' => 'unknown',
                    'total_absences_this_year' => 0,
                    'attendance_percentage_last_30_days' => 100,
                    'exceeds_18_absence_limit' => false
                ];
                $student->recommendations = [
                    'areas_of_concern' => [],
                    'recommended_next_steps' => []
                ];
                $student->risk_color = 'text-gray-500 bg-gray-100';
            }
            return $student;
        });
    }

    /**
     * Generate summary statistics for students
     */
    private function generateStudentsSummary($students): array
    {
        $total = $students->count();
        
        if ($total === 0) {
            return [
                'total_students' => 0,
                'risk_breakdown' => [],
                'attendance_average' => 0,
                'critical_cases' => 0
            ];
        }

        $riskBreakdown = $students->groupBy('analytics.risk_level')->map->count();
        $attendanceAvg = $students->avg('analytics.attendance_percentage_last_30_days');
        $criticalCases = $students->where('analytics.exceeds_18_absence_limit', true)->count();

        return [
            'total_students' => $total,
            'risk_breakdown' => $riskBreakdown,
            'attendance_average' => round($attendanceAvg, 1),
            'critical_cases' => $criticalCases
        ];
    }

    /**
     * Generate overall summary for grouped data
     */
    private function generateOverallSummary(array $groups): array
    {
        $totalStudents = 0;
        $allRiskLevels = [];
        $allAttendanceRates = [];
        $totalCritical = 0;

        foreach ($groups as $group) {
            if (isset($group['summary'])) {
                $totalStudents += $group['summary']['total_students'];
                $allRiskLevels = array_merge($allRiskLevels, $group['summary']['risk_breakdown']->toArray());
                $allAttendanceRates[] = $group['summary']['attendance_average'];
                $totalCritical += $group['summary']['critical_cases'];
            }
        }

        return [
            'total_students' => $totalStudents,
            'overall_attendance_average' => count($allAttendanceRates) > 0 ? round(array_sum($allAttendanceRates) / count($allAttendanceRates), 1) : 0,
            'total_critical_cases' => $totalCritical,
            'risk_distribution' => collect($allRiskLevels)->groupBy(function ($item, $key) {
                return $key;
            })->map->sum()
        ];
    }

    /**
     * Get risk level color for UI
     */
    private function getRiskColor(string $riskLevel): string
    {
        $colors = [
            'low' => 'text-green-600 bg-green-100 border-green-200',
            'medium' => 'text-yellow-600 bg-yellow-100 border-yellow-200',
            'high' => 'text-orange-600 bg-orange-100 border-orange-200',
            'critical' => 'text-red-600 bg-red-100 border-red-200'
        ];

        return $colors[$riskLevel] ?? 'text-gray-500 bg-gray-100 border-gray-200';
    }

    /**
     * Create notification for status change
     */
    private function createStatusChangeNotification(Student $student, StudentStatusChange $statusChange): void
    {
        // Get admin users (you may need to adjust this based on your user roles system)
        $adminUsers = \App\Models\User::where('role', 'admin')->get();

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

    /**
     * Get teacher ID from request or authenticated user
     */
    private function getTeacherId(Request $request): ?int
    {
        if ($request->has('teacher_id')) {
            return $request->teacher_id;
        }

        if (Auth::check()) {
            $user = Auth::user();
            $teacher = Teacher::where('user_id', $user->id)->first();
            return $teacher ? $teacher->id : null;
        }

        return null;
    }

    /**
     * Verify that a student belongs to the teacher
     */
    private function verifyStudentBelongsToTeacher(int $teacherId, int $studentId): bool
    {
        return Student::where('id', $studentId)
            ->whereHas('sections', function ($query) use ($teacherId) {
                $query->whereHas('teacherAssignments', function ($tq) use ($teacherId) {
                    $tq->where('teacher_id', $teacherId)
                      ->where('is_active', true);
                });
            })
            ->exists();
    }
}
