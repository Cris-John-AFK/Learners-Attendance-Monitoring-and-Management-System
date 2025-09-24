<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use App\Models\TeacherSectionSubject;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Get teachers with their assignments and homeroom data
            $teachers = Teacher::with(['user', 'assignments.section', 'assignments.subject'])
                ->get()
                ->map(function ($teacher) {
                    $primaryAssignment = null;
                    $subjectAssignments = [];
                    
                    // First, check if this teacher is a homeroom teacher for any section
                    $homeroomSection = DB::table('sections')
                        ->where('homeroom_teacher_id', $teacher->id)
                        ->first();
                    
                    if ($homeroomSection) {
                        $primaryAssignment = [
                            'id' => 'homeroom_' . $homeroomSection->id,
                            'section' => [
                                'id' => $homeroomSection->id,
                                'name' => $homeroomSection->name,
                                'grade' => $this->getGradeFromSection($homeroomSection)
                            ],
                            'subject' => [
                                'id' => null,
                                'name' => 'Homeroom'
                            ]
                        ];
                    }
                    
                    // Then process regular subject assignments
                    foreach ($teacher->assignments as $assignment) {
                        // If no homeroom assignment found, check for primary role assignments
                        if (!$primaryAssignment && ($assignment->role === 'homeroom_teacher' || $assignment->role === 'primary')) {
                            $primaryAssignment = [
                                'id' => $assignment->id,
                                'section' => [
                                    'id' => $assignment->section->id,
                                    'name' => $assignment->section->name,
                                    'grade' => $this->getGradeFromSection($assignment->section)
                                ],
                                'subject' => [
                                    'id' => $assignment->subject->id ?? null,
                                    'name' => $assignment->subject->name ?? 'Homeroom'
                                ]
                            ];
                        } else {
                            $subjectAssignments[] = [
                                'id' => $assignment->id,
                                'section' => [
                                    'id' => $assignment->section->id,
                                    'name' => $assignment->section->name,
                                    'grade' => $this->getGradeFromSection($assignment->section)
                                ],
                                'subject' => [
                                    'id' => $assignment->subject->id ?? null,
                                    'name' => $assignment->subject->name ?? 'N/A'
                                ]
                            ];
                        }
                    }

                    return [
                        'id' => $teacher->id,
                        'first_name' => $teacher->first_name,
                        'last_name' => $teacher->last_name,
                        'phone_number' => $teacher->phone_number,
                        'email' => $teacher->user->email ?? null,
                        'username' => $teacher->user->username ?? null,
                        'is_active' => $teacher->user->is_active ?? false,
                        'primary_assignment' => $primaryAssignment,
                        'subject_assignments' => $subjectAssignments,
                    ];
                });

            Log::info("Retrieved {$teachers->count()} teachers successfully");
            return response()->json($teachers);
        } catch (\Exception $e) {
            Log::error('Error retrieving teachers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch teachers'], 500);
        }
    }

    /**
     * Get grade information from section
     */
    private function getGradeFromSection($section)
    {
        try {
            // Get the curriculum_grade relationship for this section
            $curriculumGrade = DB::table('curriculum_grade as cg')
                ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                ->where('cg.id', $section->curriculum_grade_id ?? 0)
                ->select('g.id', 'g.name', 'g.code')
                ->first();

            if ($curriculumGrade) {
                return [
                    'id' => $curriculumGrade->id,
                    'name' => $curriculumGrade->name,
                    'code' => $curriculumGrade->code
                ];
            }

            // Fallback: try to infer grade from section name
            $sectionName = strtolower($section->name);
            if (strpos($sectionName, 'kinder') !== false) {
                if (strpos($sectionName, 'one') !== false || strpos($sectionName, '1') !== false) {
                    return ['id' => null, 'name' => 'Kindergarten 1', 'code' => 'K1'];
                } elseif (strpos($sectionName, 'two') !== false || strpos($sectionName, '2') !== false) {
                    return ['id' => null, 'name' => 'Kindergarten 2', 'code' => 'K2'];
                }
                return ['id' => null, 'name' => 'Kindergarten', 'code' => 'K'];
            }

            // Extract grade number from section name
            if (preg_match('/grade\s*(\d+)|\bgrade\s*(\d+)\b|\b(\d+)\b/', $sectionName, $matches)) {
                $gradeNum = $matches[1] ?? $matches[2] ?? $matches[3];
                return ['id' => null, 'name' => "Grade {$gradeNum}", 'code' => "G{$gradeNum}"];
            }

            return ['id' => null, 'name' => 'Unknown Grade', 'code' => 'UNK'];
        } catch (\Exception $e) {
            Log::warning('Error getting grade from section: ' . $e->getMessage());
            return ['id' => null, 'name' => 'Unknown Grade', 'code' => 'UNK'];
        }
    }

    /**
     * Get active teachers
     */
    public function getActiveTeachers()
    {
        try {
            $teachers = Teacher::active()
                ->select(['id', 'first_name', 'last_name', 'phone_number', 'user_id'])
                ->with(['user:id,email,username,is_active'])
                ->get()
                ->map(function ($teacher) {
                    return [
                        'id' => $teacher->id,
                        'first_name' => $teacher->first_name,
                        'last_name' => $teacher->last_name,
                        'phone_number' => $teacher->phone_number,
                        'email' => $teacher->user->email ?? null,
                        'username' => $teacher->user->username ?? null,
                        'is_active' => $teacher->user->is_active ?? false,
                    ];
                });

            Log::info("Retrieved {$teachers->count()} active teachers successfully");
            return response()->json($teachers);
        } catch (\Exception $e) {
            Log::error('Error fetching active teachers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch active teachers'], 500);
        }
    }

    /**
     * Get teachers by section
     */
    public function getTeachersBySection($sectionId)
    {
        $teachers = Teacher::whereHas('assignments', function ($query) use ($sectionId) {
            $query->where('section_id', $sectionId)
                ->where('is_active', true);
        })->with(['assignments' => function ($query) use ($sectionId) {
            $query->where('section_id', $sectionId)
                ->where('is_active', true)
                ->with(['subject']);
        }])->get();
        return response()->json($teachers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'is_head_teacher' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'teacher',
                'is_active' => true,
                'force_password_reset' => true
            ]);

            // Create teacher profile
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'is_head_teacher' => $request->is_head_teacher ?? false,
            ]);

            DB::commit();

            $teacherData = $teacher->toArray();
            $teacherData['email'] = $user->email;
            $teacherData['username'] = $user->username;
            $teacherData['is_active'] = $user->is_active;
            $teacherData['status'] = $teacher->status;

            return response()->json($teacherData, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create teacher: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher)
    {
        // Load relationships with additional details
        $teacher->load(['assignments.section.grade', 'assignments.subject', 'user']);

        // Add user data
        $teacherData = $teacher->toArray();
        $teacherData['email'] = $teacher->user->email;
        $teacherData['username'] = $teacher->user->username;
        $teacherData['is_active'] = $teacher->user->is_active;
        $teacherData['status'] = $teacher->status;

        return response()->json($teacherData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Teacher $teacher)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $teacher->user_id,
            'username' => 'required|string|unique:users,username,' . $teacher->user_id,
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'is_head_teacher' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Update user account
            $teacher->user->update([
                'username' => $request->username,
                'email' => $request->email,
            ]);

            // Update teacher profile
            $teacher->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'is_head_teacher' => $request->is_head_teacher ?? $teacher->is_head_teacher,
            ]);

            DB::commit();

            // Return the updated teacher
            $teacher->refresh();
            $teacher->load(['assignments.section.grade', 'assignments.subject']);

            $teacherData = $teacher->toArray();
            $teacherData['email'] = $teacher->user->email;
            $teacherData['username'] = $teacher->user->username;
            $teacherData['is_active'] = $teacher->user->is_active;
            $teacherData['status'] = $teacher->status;

            return response()->json($teacherData);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update teacher: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Teacher $teacher)
    {
        try {
            DB::beginTransaction();

            // First archive teacher's assignments
            $teacher->assignments()->delete();

            // Then delete teacher record (soft delete)
            $teacher->delete();

            // Deactivate user account
            $teacher->user->update(['is_active' => false]);

            DB::commit();
            return response()->json(['message' => 'Teacher removed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to remove teacher: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle teacher status
     */
    public function toggleStatus(Teacher $teacher)
    {
        try {
            $teacher->user->update([
                'is_active' => !$teacher->user->is_active
            ]);
            return response()->json(['message' => 'Teacher status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update teacher status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update teacher assignments
     */
    public function updateAssignments(Request $request, Teacher $teacher)
    {
        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array',
            'assignments.*.section_id' => 'required|exists:sections,id',
            'assignments.*.subject_id' => 'nullable|exists:subjects,id', // Changed from required to nullable
            'assignments.*.is_primary' => 'boolean',
            'assignments.*.role' => 'string|in:primary,subject,special_education,assistant,co_teacher,counselor' // Added co_teacher and counselor
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for teacher assignments: ' . json_encode($validator->errors()));
            Log::error('Request data: ' . json_encode($request->all()));
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Process assignments to ensure role and is_primary are synchronized
            $processedAssignments = [];
            foreach ($request->assignments as $assignment) {
                // Ensure consistency between role and is_primary
                $isPrimary = $assignment['is_primary'] ?? false;
                $role = $assignment['role'] ?? 'subject';

                if ($role === 'primary') {
                    $isPrimary = true;
                }
                if ($isPrimary && $role !== 'primary') {
                    $role = 'primary';
                }

                $processedAssignments[] = [
                    'section_id' => $assignment['section_id'],
                    'subject_id' => $assignment['subject_id'],
                    'is_primary' => $isPrimary,
                    'role' => $role,
                    'id' => $assignment['id'] ?? null
                ];
            }

            // All your validation code remains the same...
            // Implement validation checks for primary teacher conflicts, etc.

            // Check for primary teacher conflicts (keep your existing code)
            $primaryAssignments = array_filter($processedAssignments, function($a) {
                return $a['is_primary'] === true || $a['role'] === 'primary';
            });

            // Rest of your validation code...

        // Rest of your validation code...

        // Instead of deleting and recreating all assignments, identify what needs to be created
        // Get existing assignments IDs
        $existingAssignmentIds = $teacher->assignments()->pluck('id')->toArray();

        // Split into existing and new assignments
        $assignmentsToUpdate = [];
        $assignmentsToCreate = [];
        $processedIds = [];

        // Process each assignment to determine if it's new or existing
        foreach ($processedAssignments as $assignment) {
            // Check if this assignment already exists
            $existingAssignment = $teacher->assignments()
                ->where('section_id', $assignment['section_id'])
                ->where('subject_id', $assignment['subject_id'])
                ->first();

            if ($existingAssignment) {
                // Update existing assignment
                $assignment['id'] = $existingAssignment->id;
                $assignmentsToUpdate[] = $assignment;
                $processedIds[] = $existingAssignment->id;
            } else {
                // Create new assignment
                $assignment['teacher_id'] = $teacher->id;
                $assignmentsToCreate[] = $assignment;
            }
        }

        // Update the duplicate subject-section validation check
        foreach ($processedAssignments as $assignment) {
            // Skip if not a subject teacher role
            if ($assignment['is_primary'] === true || $assignment['role'] === 'primary') {
                continue;
            }

            // Special handling for Homeroom subject
            if (strtolower($assignment['subject_name'] ?? '') === 'homeroom') {
                $query = TeacherSectionSubject::where('section_id', $assignment['section_id'])
                    ->where('subject_id', $assignment['subject_id'])
                    ->where('teacher_id', '!=', $teacher->id);

                // Exclude the current assignment if we're updating
                if (!empty($assignment['id'])) {
                    $query->where('id', '!=', $assignment['id']);
                }

                $existingAssignment = $query->first();

                if ($existingAssignment) {
                    // Get teacher info for better error messages
                    $existingTeacher = Teacher::find($existingAssignment->teacher_id);
                    $teacherName = $existingTeacher ? $existingTeacher->full_name : 'Another teacher';

                    DB::rollBack();
                    return response()->json([
                        'message' => "Homeroom is already assigned to {$teacherName} for this section. Only one teacher can be assigned the Homeroom subject per section.",
                        'section_id' => $assignment['section_id'],
                        'subject_id' => $assignment['subject_id'],
                        'existing_teacher' => $existingTeacher ? [
                            'id' => $existingTeacher->id,
                            'name' => $existingTeacher->full_name
                        ] : null
                    ], 422);
                }
            }

            // For non-homeroom subjects, allow multiple sections to have the same subject
            // No validation needed here
        }
            // Get IDs for assignments to delete (ones that exist but are not in our processed list)
            $idsToDelete = array_diff($existingAssignmentIds, $processedIds);

            // Update existing assignments
            foreach ($assignmentsToUpdate as $assignment) {
                TeacherSectionSubject::where('id', $assignment['id'])
                    ->update([
                        'section_id' => $assignment['section_id'],
                        'subject_id' => $assignment['subject_id'],
                        'is_primary' => $assignment['is_primary'],
                        'is_active' => true,
                        'role' => $assignment['role']
                    ]);
            }

            // Delete removed assignments
            if (!empty($idsToDelete)) {
                TeacherSectionSubject::whereIn('id', $idsToDelete)->delete();
            }

            // Create new assignments
            foreach ($assignmentsToCreate as $assignment) {
                // Check if assignment already exists (to avoid unique constraint violations)
                $exists = TeacherSectionSubject::where('teacher_id', $teacher->id)
                    ->where('section_id', $assignment['section_id'])
                    ->where('subject_id', $assignment['subject_id'])
                    ->exists();

                // Only create if it doesn't exist
                if (!$exists) {
                    TeacherSectionSubject::create($assignment);
                }
            }

            DB::commit();

            // Reload the teacher with fresh data
            $teacher->refresh();
            $teacher->load(['assignments.section.grade', 'assignments.subject', 'user']);

            // Return the teacher with user data
            $teacherData = $teacher->toArray();
            $teacherData['email'] = $teacher->user->email;
            $teacherData['username'] = $teacher->user->username;
            $teacherData['is_active'] = $teacher->user->is_active;
            $teacherData['status'] = $teacher->status;

            return response()->json($teacherData);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update assignments: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Force password reset
     */
    public function forcePasswordReset(Teacher $teacher)
    {
        try {
            $teacher->user->update([
                'force_password_reset' => true,
                'password_changed_at' => null
            ]);
            return response()->json(['message' => 'Password reset flag set successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to set password reset flag: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Restore a soft-deleted teacher
     */
    public function restore($id)
    {
        $teacher = Teacher::withTrashed()->findOrFail($id);
        $teacher->restore();

        // Reload with relationships
        $teacher->load(['assignments.section.grade', 'assignments.subject', 'user']);

        // Return with user data
        $teacherData = $teacher->toArray();
        $teacherData['email'] = $teacher->user->email;
        $teacherData['username'] = $teacher->user->username;
        $teacherData['is_active'] = $teacher->user->is_active;
        $teacherData['status'] = $teacher->status;

        return response()->json($teacherData);
    }
}
