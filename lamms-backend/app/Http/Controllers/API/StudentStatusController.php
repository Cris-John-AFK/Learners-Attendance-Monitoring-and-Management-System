<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Models\Teacher;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudentStatusController extends Controller
{
    /**
     * Get students for teacher's learner status management
     */
    public function getStudentsForTeacher(Request $request, $teacherId)
    {
        try {
            $viewType = $request->input('view_type', 'section'); // section, subject, all

            $teacher = Teacher::with('assignments')->find($teacherId);
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found'
                ], 404);
            }

            // Get teacher's assignments with section details
            $assignments = $teacher->assignments()->with('section')->get();

            Log::info('Teacher assignments:', [
                'teacher_id' => $teacherId,
                'assignments_count' => $assignments->count(),
                'assignments' => $assignments->map(fn($a) => [
                    'id' => $a->id,
                    'section_id' => $a->section_id,
                    'section_name' => $a->section ? $a->section->name : null,
                    'is_primary' => $a->is_primary
                ])
            ]);

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'students' => [],
                    'is_section_adviser' => false
                ]);
            }

            // Check if teacher is section adviser (is_primary = true)
            $isSectionAdviser = $assignments->where('is_primary', true)->isNotEmpty();
            Log::info('Is section adviser:', ['value' => $isSectionAdviser]);

            $students = collect();

            if ($viewType === 'section') {
                // Get students from teacher's primary section (if section adviser)
                $primaryAssignments = $assignments->where('is_primary', true);
                $sectionNames = $primaryAssignments->map(function($assignment) {
                    return $assignment->section ? $assignment->section->name : null;
                })->filter()->unique();

                Log::info('Section view - Primary section names:', ['names' => $sectionNames->toArray()]);

                if ($sectionNames->isNotEmpty()) {
                    $students = Student::whereIn('section', $sectionNames)->get();
                    Log::info('Students found:', ['count' => $students->count()]);
                }
            } elseif ($viewType === 'subject') {
                // Get students from teacher's assigned subjects
                $sectionNames = $assignments->map(function($assignment) {
                    return $assignment->section ? $assignment->section->name : null;
                })->filter()->unique();

                $students = Student::whereIn('section', $sectionNames)->get();
            } else { // all
                // Get all students from all teacher's sections
                $sectionNames = $assignments->map(function($assignment) {
                    return $assignment->section ? $assignment->section->name : null;
                })->filter()->unique();

                $students = Student::whereIn('section', $sectionNames)->get();
            }

            // Format student data with status information
            $formattedStudents = $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'student_id' => $student->studentId,
                    'name' => $student->name ?? ($student->firstName . ' ' . $student->lastName),
                    'grade_level' => $student->gradeLevel,
                    'section' => $student->section,
                    'enrollment_status' => $student->enrollment_status ?? 'active',
                    'dropout_reason' => $student->dropout_reason,
                    'dropout_reason_category' => $student->dropout_reason_category,
                    'status_effective_date' => $student->status_effective_date,
                    'email' => $student->email,
                    'contact_info' => $student->contactInfo
                ];
            });

            return response()->json([
                'success' => true,
                'students' => $formattedStudents,
                'is_section_adviser' => $isSectionAdviser,
                'view_type' => $viewType
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching students: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update student enrollment status
     */
    public function updateStudentStatus(Request $request, $studentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'teacher_id' => 'required|exists:teachers,id',
                'new_status' => 'required|in:active,dropped_out,transferred_out,transferred_in',
                'reason' => 'required_if:new_status,dropped_out,transferred_out',
                'reason_category' => 'required_if:new_status,dropped_out,transferred_out',
                'effective_date' => 'required|date',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Check if teacher is section adviser (is_primary)
            $teacher = Teacher::with('assignments.section')->find($request->teacher_id);
            $isSectionAdviser = $teacher->assignments()
                ->where('is_primary', true)
                ->filter(function($assignment) use ($student) {
                    return $assignment->section && $assignment->section->name === $student->section;
                })
                ->isNotEmpty();

            if (!$isSectionAdviser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only section advisers can update student status'
                ], 403);
            }

            DB::beginTransaction();

            // Store previous status
            $previousStatus = $student->enrollment_status ?? 'active';

            // Create history record
            StudentStatusHistory::create([
                'student_id' => $studentId,
                'previous_status' => $previousStatus,
                'new_status' => $request->new_status,
                'reason' => $request->reason,
                'reason_category' => $request->reason_category,
                'effective_date' => $request->effective_date,
                'changed_by_teacher_id' => $request->teacher_id,
                'notes' => $request->notes
            ]);

            // Update student status
            $student->update([
                'enrollment_status' => $request->new_status,
                'dropout_reason' => $request->reason,
                'dropout_reason_category' => $request->reason_category,
                'status_effective_date' => $request->effective_date
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student status updated successfully',
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'enrollment_status' => $student->enrollment_status,
                    'dropout_reason' => $student->dropout_reason,
                    'status_effective_date' => $student->status_effective_date
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating student status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status history for a student
     */
    public function getStudentStatusHistory($studentId)
    {
        try {
            $history = StudentStatusHistory::where('student_id', $studentId)
                ->with('teacher:id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'previous_status' => $record->previous_status,
                        'new_status' => $record->new_status,
                        'reason' => $record->reason,
                        'reason_category' => $record->reason_category,
                        'effective_date' => $record->effective_date,
                        'changed_by' => $record->teacher ?
                            $record->teacher->first_name . ' ' . $record->teacher->last_name :
                            'Unknown',
                        'notes' => $record->notes,
                        'changed_at' => $record->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching status history: ' . $e->getMessage()
            ], 500);
        }
    }
}
