<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    /**
     * Unified login for all user types (admin, teacher, guardhouse)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        try {
            // Find user by email OR username
            $user = User::where('email', $request->email)
                        ->orWhere('username', $request->email)
                        ->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact the administrator.'
                ], 403);
            }

            // Check for existing active sessions (enforce one session per user)
            $existingSessions = UserSession::where('user_id', $user->id)->get();
            
            if ($existingSessions->count() > 0) {
                // Revoke all existing tokens
                foreach ($existingSessions as $session) {
                    // Delete the Sanctum token
                    $user->tokens()->where('name', $session->token)->delete();
                }
                
                // Delete all existing sessions
                UserSession::where('user_id', $user->id)->delete();
                
                Log::info("Revoked existing sessions for user: {$user->id}");
            }

            // Get user profile based on role
            $profile = null;
            switch ($user->role) {
                case 'teacher':
                    // Load teacher with assignments for complete data
                    $teacherProfile = $user->teacher()->with(['assignments.section', 'assignments.subject'])->first();
                    
                    if ($teacherProfile) {
                        // Also check if this teacher is a homeroom teacher for any section
                        $homeroomSection = DB::table('sections')
                            ->where('homeroom_teacher_id', $teacherProfile->id)
                            ->first();
                        
                        if ($homeroomSection) {
                            // Get the grade info for homeroom section
                            $grade = DB::table('curriculum_grade as cg')
                                ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                                ->where('cg.id', $homeroomSection->curriculum_grade_id)
                                ->select('g.id', 'g.name', 'g.level', 'g.code')
                                ->first();
                            
                            // Add homeroom section info to teacher profile
                            $teacherProfile->homeroom_section = [
                                'id' => $homeroomSection->id,
                                'name' => $homeroomSection->name,
                                'grade' => $grade ? [
                                    'id' => $grade->id,
                                    'name' => $grade->name,
                                    'level' => $grade->level,
                                    'code' => $grade->code
                                ] : null
                            ];
                        }
                        
                        $profile = $teacherProfile;
                    }
                    break;
                case 'admin':
                    $profile = $user->admin;
                    break;
                case 'guardhouse':
                    $profile = $user->guardhouseUser;
                    break;
            }

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'User profile not found. Please contact the administrator.'
                ], 404);
            }

            // Create new Sanctum token
            $tokenName = "{$user->role}_{$user->id}_" . now()->timestamp;
            $token = $user->createToken($tokenName)->plainTextToken;

            // Create session record
            $userSession = UserSession::create([
                'user_id' => $user->id,
                'token' => $tokenName,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_activity' => now(),
                'expires_at' => now()->addHours(8), // 8 hour session
            ]);

            Log::info("User logged in successfully", [
                'user_id' => $user->id,
                'role' => $user->role,
                'email' => $user->email,
                'session_id' => $userSession->id
            ]);

            // CRITICAL FIX: For teachers, format assignments with complete subject info
            if ($user->role === 'teacher' && isset($profile->assignments)) {
                $formattedAssignments = [];
                foreach ($profile->assignments as $assignment) {
                    $formattedAssignments[] = [
                        'id' => $assignment->id,
                        'section_id' => $assignment->section_id,
                        'subject_id' => $assignment->subject_id,
                        'section_name' => $assignment->section ? $assignment->section->name : 'Unknown Section',
                        'subject_name' => $assignment->subject ? $assignment->subject->name : 'Homeroom',
                        'grade_name' => $assignment->section && $assignment->section->curriculumGrade 
                            ? $assignment->section->curriculumGrade->grade->name 
                            : 'Unknown Grade',
                        'role' => $assignment->role ?? 'subject',
                        'is_primary' => $assignment->is_primary ?? false
                    ];
                }
                $profile->formatted_assignments = $formattedAssignments;
            }

            // Return response based on role
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role' => $user->role,
                    ],
                    'profile' => $profile,
                    'session' => [
                        'id' => $userSession->id,
                        'expires_at' => $userSession->expires_at,
                    ]
                ]
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Login error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ], 500);
        }
    }

    /**
     * Logout user and revoke token
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Delete user sessions
            UserSession::where('user_id', $user->id)->delete();

            // Revoke all tokens for this user
            $user->tokens()->delete();

            Log::info("User logged out successfully", [
                'user_id' => $user->id,
                'role' => $user->role
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Logout error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout'
            ], 500);
        }
    }

    /**
     * Get authenticated user details
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $profile = $user->getProfile();
            $session = UserSession::where('user_id', $user->id)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role' => $user->role,
                    ],
                    'profile' => $profile,
                    'session' => $session ? [
                        'last_activity' => $session->last_activity,
                        'expires_at' => $session->expires_at,
                        'is_active' => $session->isActive(),
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Me endpoint error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Check session validity
     */
    public function checkSession(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => 'No active session'
                ], 401);
            }

            $session = UserSession::where('user_id', $user->id)->first();

            if (!$session || $session->isExpired()) {
                // Session expired, clean up
                if ($session) {
                    $session->delete();
                }
                $user->tokens()->delete();

                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Session expired'
                ], 401);
            }

            // Update last activity
            $session->update(['last_activity' => now()]);

            return response()->json([
                'success' => true,
                'valid' => true,
                'data' => [
                    'expires_at' => $session->expires_at,
                    'time_remaining' => $session->expires_at->diffInMinutes(now())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Check session error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
}
