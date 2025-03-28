<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = Teacher::with(['user', 'assignments.section', 'assignments.subject'])
            ->get()
            ->map(function ($teacher) {
                $teacherData = $teacher->toArray();
                $teacherData['email'] = $teacher->user->email;
                $teacherData['username'] = $teacher->user->username;
                $teacherData['is_active'] = $teacher->user->is_active;
                $teacherData['active_assignments'] = $teacher->getActiveAssignments();
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
            ->with(['assignments.section', 'assignments.subject'])
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
        return response()->json($teacher->load(['assignments.section', 'assignments.subject']));
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

            // Update password if provided
            if ($request->filled('password')) {
                $teacher->user->update([
                    'password' => Hash::make($request->password),
                    'force_password_reset' => true,
                    'password_changed_at' => null
                ]);
            }

            // Update teacher profile
            $teacher->update([
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
            $teacherData['email'] = $teacher->user->email;
            $teacherData['username'] = $teacher->user->username;
            $teacherData['is_active'] = $teacher->user->is_active;

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
            $teacher->user->delete(); // This will cascade delete the teacher record
            DB::commit();
            return response()->json(['message' => 'Teacher deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete teacher: ' . $e->getMessage()], 500);
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
            'assignments.*.is_primary' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Remove old assignments
        $teacher->assignments()->delete();

        // Add new assignments
        foreach ($request->assignments as $assignment) {
            $teacher->assignments()->create([
                'section_id' => $assignment['section_id'],
                'subject_id' => $assignment['subject_id'],
                'is_primary' => $assignment['is_primary'] ?? false,
                'is_active' => true
            ]);
        }

        return response()->json($teacher->load(['assignments.section', 'assignments.subject']));
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
        return response()->json($teacher->load(['assignments.section', 'assignments.subject']));
    }
}
