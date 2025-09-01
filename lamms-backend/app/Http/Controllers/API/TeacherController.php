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
            // Lightweight query with only essential fields and relationships (removed 'status' column that doesn't exist)
            $teachers = Teacher::select(['id', 'first_name', 'last_name', 'phone_number', 'user_id'])
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

            Log::info("Retrieved {$teachers->count()} teachers successfully");
            return response()->json($teachers);
        } catch (\Exception $e) {
            Log::error('Error fetching teachers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch teachers'], 500);
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

            // Instead of deleting and recreating all assignments, identify what needs to be created
            // Get existing assignments IDs
            $existingAssignmentIds = $teacher->assignments()->pluck('id')->toArray();

            // Split into existing and new assignments
            $assignmentsToUpdate = [];
            $assignmentsToCreate = [];
            $processedIds = [];

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
