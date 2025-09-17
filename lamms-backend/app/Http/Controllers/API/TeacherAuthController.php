<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAuthController extends Controller
{
    /**
     * Teacher login
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            // Find user by username with teacher role
            $user = DB::table('users')
                ->where('username', $request->username)
                ->where('role', 'teacher')
                ->where('is_active', true)
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'username' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Get teacher details
            $teacher = DB::table('teachers')
                ->where('user_id', $user->id)
                ->first();

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found'
                ], 404);
            }

            // Get teacher assignments - handle both null and non-null subjects
            $assignments = DB::table('teacher_section_subject as tss')
                ->join('sections as s', 'tss.section_id', '=', 's.id')
                ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
                ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                ->where('tss.teacher_id', $teacher->id)
                ->where('tss.is_active', true)
                ->whereNull('tss.deleted_at') // Ensure soft-deleted records are excluded
                ->select(
                    'tss.id as assignment_id',
                    'tss.section_id',
                    'tss.subject_id',
                    'tss.role',
                    'tss.is_primary',
                    's.name as section_name',
                    DB::raw('COALESCE(sub.name, \'Homeroom\') as subject_name'),
                    'g.name as grade_name',
                    'g.code as grade_code'
                )
                ->get();

            // Create session token
            $token = bin2hex(random_bytes(32));
            
            // Store session in database (create sessions table if needed)
            $sessionId = DB::table('teacher_sessions')->insertGetId([
                'teacher_id' => $teacher->id,
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
                'expires_at' => null // Session until logout
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'teacher' => [
                        'id' => $teacher->id,
                        'first_name' => $teacher->first_name,
                        'last_name' => $teacher->last_name,
                        'full_name' => $teacher->first_name . ' ' . $teacher->last_name,
                        'is_head_teacher' => $teacher->is_head_teacher
                    ],
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role
                    ],
                    'assignments' => $assignments,
                    'token' => $token,
                    'session_id' => $sessionId
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Teacher logout
     */
    public function logout(Request $request)
    {
        try {
            $token = $request->bearerToken() ?? $request->header('Authorization');
            
            if ($token) {
                // Remove Bearer prefix if present
                $token = str_replace('Bearer ', '', $token);
                
                // Delete session
                DB::table('teacher_sessions')
                    ->where('token', hash('sha256', $token))
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current teacher profile and assignments
     */
    public function profile(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get teacher assignments with detailed information
            $assignments = DB::table('teacher_section_subject as tss')
                ->join('sections as s', 'tss.section_id', '=', 's.id')
                ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
                ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                ->where('tss.teacher_id', $teacher->teacher_id)
                ->where('tss.is_active', true)
                ->whereNull('tss.deleted_at') // Ensure soft-deleted records are excluded
                ->select(
                    'tss.id as assignment_id',
                    'tss.section_id',
                    'tss.subject_id',
                    'tss.role',
                    'tss.is_primary',
                    's.name as section_name',
                    DB::raw('COALESCE(sub.name, \'Homeroom\') as subject_name'),
                    'g.name as grade_name',
                    'g.code as grade_code'
                )
                ->get();

            // Get student counts per assignment
            foreach ($assignments as $assignment) {
                $studentCount = DB::table('student_section')
                    ->where('section_id', $assignment->section_id)
                    ->where('is_active', true)
                    ->count();
                $assignment->student_count = $studentCount;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'teacher' => $teacher,
                    'assignments' => $assignments
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Middleware helper to get authenticated teacher
     */
    private function getAuthenticatedTeacher(Request $request)
    {
        $token = $request->bearerToken() ?? $request->header('Authorization');
        
        if (!$token) {
            return null;
        }

        // Remove Bearer prefix if present
        $token = str_replace('Bearer ', '', $token);

        // Get session
        $session = DB::table('teacher_sessions as ts')
            ->join('teachers as t', 'ts.teacher_id', '=', 't.id')
            ->join('users as u', 'ts.user_id', '=', 'u.id')
            ->where('ts.token', hash('sha256', $token))
            ->where('u.is_active', true)
            ->select(
                't.id as teacher_id',
                't.first_name',
                't.last_name',
                't.is_head_teacher',
                'u.id as user_id',
                'u.username',
                'u.email',
                'u.role'
            )
            ->first();

        return $session;
    }
}
