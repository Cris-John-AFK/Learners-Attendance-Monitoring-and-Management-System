<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Get current token name
        $currentToken = $user->currentAccessToken();
        
        if (!$currentToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token'
            ], 401);
        }

        // Check if session exists for this token
        $session = UserSession::where('user_id', $user->id)
            ->where('token', $currentToken->name)
            ->first();

        if (!$session) {
            // Session not found, token is invalid
            $currentToken->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Session not found. Please login again.',
                'session_expired' => true
            ], 401);
        }

        // Check if session is expired
        if ($session->isExpired()) {
            // Clean up expired session
            $session->delete();
            $user->tokens()->delete();
            
            Log::info("Session expired for user: {$user->id}");
            
            return response()->json([
                'success' => false,
                'message' => 'Your session has expired. Please login again.',
                'session_expired' => true
            ], 401);
        }

        // Update last activity
        $session->update(['last_activity' => now()]);

        return $next($request);
    }
}
