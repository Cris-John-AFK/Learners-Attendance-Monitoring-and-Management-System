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
        $teachers = Teacher::with(['user', 'assignments.section.grade', 'assignments.subject'])
            ->get()
            ->map(function ($teacher) {
                $teacherData = $teacher->toArray();
                $teacherData['email'] = $teacher->user->email;
                $teacherData['username'] = $teacher->user->username;
                $teacherData['is_active'] = $teacher->user->is_active;
                $teacherData['status'] = $teacher->status;
                // These fields are automatically included due to the appends in the Teacher model
                // $teacherData['primary_assignment'] = $teacher->primary_assignment;
                // $teacherData['subject_assignments'] = $teacher->subject_assignments;
                return $teacherData;
            });

        return response()->json($teachers);
    }

    /**
     * Get active teachers
     */
    public function getActiveTeachers()
    {
        $teachers = Teacher::active()
            ->with(['assignments.section.grade', 'assignments.subject'])
            ->get();
        return response()->json($teachers);
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
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.is_primary' => 'boolean',
            'assignments.*.role' => 'string|in:primary,subject,special_education,assistant'
        ]);

        if ($validator->fails()) {
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

            // Check for primary teacher conflicts
            $primaryAssignments = array_filter($processedAssignments, function($a) {
                return $a['is_primary'] === true || $a['role'] === 'primary';
            });

            // Check if there are multiple primary assignments for the same teacher
            if (count($primaryAssignments) > 1) {
                DB::rollBack();
                return response()->json([
                    'message' => 'A teacher can only have one primary assignment. Please select only one assignment as primary.',
                    'assignments' => $primaryAssignments
                ], 422);
            }

            // Check if any of the assignments conflict with existing primary teachers in other records
            foreach ($primaryAssignments as $assignment) {
                $sectionId = $assignment['section_id'];

                // Check if there's already a primary teacher for this section
                $existingPrimary = TeacherSectionSubject::where('section_id', $sectionId)
                    ->where(function($query) {
                        $query->where('is_primary', true)
                            ->orWhere('role', 'primary');
                    })
                    ->where('teacher_id', '!=', $teacher->id)
                    ->first();

                if ($existingPrimary) {
                    // Get the teacher name for better error message
                    $primaryTeacher = Teacher::find($existingPrimary->teacher_id);
                    $teacherName = $primaryTeacher ? $primaryTeacher->full_name : 'Another teacher';

                    DB::rollBack();
                    return response()->json([
                        'message' => "This section already has a primary teacher assigned ({$teacherName}). Each section can only have one primary teacher.",
                        'section_id' => $sectionId,
                        'existing_teacher' => $primaryTeacher ? [
                            'id' => $primaryTeacher->id,
                            'name' => $primaryTeacher->full_name
                        ] : null
                    ], 422);
                }
            }

            // Check for duplicate subject-section combinations (excluding updates to existing assignments)
            foreach ($processedAssignments as $assignment) {
                // Skip primary assignments as they're already validated above
                if ($assignment['is_primary'] === true || $assignment['role'] === 'primary') {
                    continue;
                }

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
                        'message' => "Another teacher ({$teacherName}) is already assigned to this section and subject.",
                        'section_id' => $assignment['section_id'],
                        'subject_id' => $assignment['subject_id'],
                        'existing_teacher' => $existingTeacher ? [
                            'id' => $existingTeacher->id,
                            'name' => $existingTeacher->full_name
                        ] : null
                    ], 422);
                }
            }

            // Check for existing records in database that have multiple primary flags for the same section
            // This is a data integrity check and data cleanup
            $duplicatePrimaryChecks = TeacherSectionSubject::where(function($query) {
                $query->where('is_primary', true)
                    ->orWhere('role', 'primary');
            })->get();

            $sectionsWithPrimary = [];
            $duplicatePrimaries = [];

            foreach ($duplicatePrimaryChecks as $check) {
                $key = $check->section_id;
                if (isset($sectionsWithPrimary[$key])) {
                    $duplicatePrimaries[] = $check;
                } else {
                    $sectionsWithPrimary[$key] = $check;
                }
            }

            // Fix any duplicate primaries by turning them into regular subject teachers
            foreach ($duplicatePrimaries as $duplicate) {
                $duplicate->is_primary = false;
                $duplicate->role = 'subject';
                $duplicate->save();
                Log::warning("Fixed duplicate primary teacher: Teacher ID {$duplicate->teacher_id} for Section ID {$duplicate->section_id}");
            }

            // Remove old assignments
            $teacher->assignments()->delete();

            // Add new assignments
            foreach ($processedAssignments as $assignment) {
                $teacher->assignments()->create([
                    'section_id' => $assignment['section_id'],
                    'subject_id' => $assignment['subject_id'],
                    'is_primary' => $assignment['is_primary'],
                    'is_active' => true,
                    'role' => $assignment['role']
                ]);
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
