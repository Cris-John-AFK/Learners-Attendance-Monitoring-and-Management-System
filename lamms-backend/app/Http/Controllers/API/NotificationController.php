<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user
     * 
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // For teacher authentication, use user_id from request
            $userId = $request->input('user_id') ?: Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Check if notifications table exists
            if (!Schema::hasTable('notifications')) {
                Log::warning("Notifications table does not exist, returning empty result");
                return $this->getEmptyNotificationsResponse();
            }

            // Try to get notifications with minimal query
            $notifications = collect();
            try {
                $notifications = Notification::where('user_id', $userId)
                    ->orderBy('id', 'desc')
                    ->limit(50)
                    ->get();
            } catch (\Exception $dbError) {
                Log::error("Database error in notifications: " . $dbError->getMessage());
                return $this->getEmptyNotificationsResponse();
            }

            // Group notifications for better organization
            $groupedNotifications = [
                'unread' => $notifications->where('is_read', false)->values(),
                'high_priority' => $notifications->whereIn('priority', ['high', 'critical'])->values(),
                'recent' => $notifications->where('created_at', '>=', now()->subDays(7))->values(),
                'all' => $notifications
            ];

            // Generate statistics
            $stats = [
                'total_notifications' => $notifications->count(),
                'unread_count' => $notifications->where('is_read', false)->count(),
                'high_priority_count' => $notifications->whereIn('priority', ['high', 'critical'])->count(),
                'type_breakdown' => $notifications->groupBy('type')->map->count(),
                'priority_breakdown' => $notifications->groupBy('priority')->map->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $groupedNotifications,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error retrieving notifications: " . $e->getMessage());
            return $this->getEmptyNotificationsResponse();
        }
    }

    /**
     * Return empty notifications response as fallback
     */
    private function getEmptyNotificationsResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => [
                    'unread' => [],
                    'high_priority' => [],
                    'recent' => [],
                    'all' => []
                ],
                'statistics' => [
                    'total_notifications' => 0,
                    'unread_count' => 0,
                    'high_priority_count' => 0,
                    'type_breakdown' => [],
                    'priority_breakdown' => []
                ]
            ]
        ]);
    }

    /**
     * Store a new notification
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info("Notification creation request received", [
                'request_data' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            // Check if notifications table exists
            if (!Schema::hasTable('notifications')) {
                Log::error("Notifications table does not exist");
                return response()->json([
                    'success' => false,
                    'message' => 'Notifications table not found',
                    'error' => 'Database table missing'
                ], 500);
            }

            $validated = $request->validate([
                'user_id' => 'required|integer',
                'type' => 'required|string|max:50',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'data' => 'nullable|array',
                'priority' => 'nullable|string|in:low,medium,high,critical',
                'related_student_id' => 'nullable|integer'
            ]);

            Log::info("Validation passed", ['validated_data' => $validated]);

            $notification = Notification::create([
                'user_id' => $validated['user_id'],
                'type' => $validated['type'],
                'title' => $validated['title'],
                'message' => $validated['message'],
                'data' => $validated['data'] ?? null,
                'priority' => $validated['priority'] ?? 'medium',
                'related_student_id' => $validated['related_student_id'] ?? null,
                'created_by_user_id' => $validated['user_id'],
                'is_read' => false
            ]);

            Log::info("Notification created successfully", [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification->fresh(),
                'message' => 'Notification created successfully'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("Validation error creating notification", [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error("Error creating notification", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     * 
     * @param int $notificationId
     * @return JsonResponse
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        try {
            // For teacher authentication, get user_id from request or use Auth::id()
            $userId = $request->input('user_id') ?: Auth::id();
            
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or not authorized'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'data' => $notification->fresh(),
                'message' => 'Notification marked as read'
            ]);

        } catch (\Exception $e) {
            Log::error("Error marking notification as read: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     * 
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            // For teacher authentication, get user_id from request or use Auth::id()
            $userId = $request->input('user_id') ?: Auth::id();
            
            $updatedCount = Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'updated_count' => $updatedCount
                ],
                'message' => "Marked {$updatedCount} notifications as read"
            ]);

        } catch (\Exception $e) {
            Log::error("Error marking all notifications as read: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get unread notification count
     * 
     * @return JsonResponse
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $count = Notification::where('user_id', Auth::id())
                ->unread()
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ],
                'message' => 'Unread count retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting unread count: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete notification
     * 
     * @param int $notificationId
     * @return JsonResponse
     */
    public function destroy(int $notificationId): JsonResponse
    {
        try {
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or not authorized'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error deleting notification: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a test notification (for development/testing)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createTestNotification(Request $request): JsonResponse
    {
        try {
            if (!config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test notifications only available in debug mode'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required|string|in:' . implode(',', array_keys(Notification::getTypes())),
                'title' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
                'priority' => 'required|string|in:' . implode(',', array_keys(Notification::getPriorities())),
                'related_student_id' => 'nullable|integer|exists:student_details,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $notification = Notification::create([
                'user_id' => Auth::id(),
                'type' => $request->type,
                'title' => $request->title,
                'message' => $request->message,
                'priority' => $request->priority,
                'related_student_id' => $request->related_student_id,
                'created_by_user_id' => Auth::id(),
                'data' => $request->input('data', [])
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification->load(['relatedStudent', 'createdBy']),
                'message' => 'Test notification created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error creating test notification: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get notification statistics for dashboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $days = $request->input('days', 30);

            $notifications = Notification::where('user_id', $userId)
                ->recent($days)
                ->get();

            $stats = [
                'total_notifications' => $notifications->count(),
                'unread_count' => $notifications->where('is_read', false)->count(),
                'high_priority_count' => $notifications->whereIn('priority', ['high', 'critical'])->count(),
                'today_count' => $notifications->where('created_at', '>=', now()->startOfDay())->count(),
                'this_week_count' => $notifications->where('created_at', '>=', now()->startOfWeek())->count(),
                'type_breakdown' => $notifications->groupBy('type')->map->count(),
                'priority_breakdown' => $notifications->groupBy('priority')->map->count(),
                'daily_trend' => $this->getDailyTrend($notifications, 7)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Notification statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting notification statistics: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get daily notification trend
     */
    private function getDailyTrend($notifications, int $days): array
    {
        $trend = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = $notifications->filter(function ($notification) use ($date) {
                return $notification->created_at->toDateString() === $date;
            })->count();
            
            $trend[] = [
                'date' => $date,
                'count' => $count
            ];
        }
        
        return $trend;
    }
}
