<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SchoolCalendarEvent;
use App\Models\Teacher;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SchoolCalendarController extends Controller
{
    /**
     * Get all calendar events
     */
    public function index(Request $request)
    {
        try {
            $query = SchoolCalendarEvent::where('is_active', true);
            
            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('end_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->where('start_date', '<=', $request->end_date);
            }
            
            // Filter by type
            if ($request->has('event_type')) {
                $query->where('event_type', $request->event_type);
            }
            
            $events = $query->orderBy('start_date', 'asc')->get();
            
            return response()->json([
                'success' => true,
                'events' => $events
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching calendar events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch calendar events'
            ], 500);
        }
    }
    
    /**
     * Create new calendar event
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'event_type' => 'required|in:holiday,half_day,early_dismissal,no_classes,school_event,teacher_training,exam_day',
                'affects_attendance' => 'boolean',
                'modified_start_time' => 'nullable|date_format:H:i',
                'modified_end_time' => 'nullable|date_format:H:i',
                'affected_sections' => 'nullable|array',
                'affected_grade_levels' => 'nullable|array'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            Log::info("Creating calendar event with data:", $request->all());
            
            $event = SchoolCalendarEvent::create([
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'event_type' => $request->event_type,
                'affects_attendance' => $request->get('affects_attendance', true),
                'modified_start_time' => $request->modified_start_time,
                'modified_end_time' => $request->modified_end_time,
                'affected_sections' => $request->affected_sections,
                'affected_grade_levels' => $request->affected_grade_levels,
                'is_active' => true,
                'created_by' => $request->user()->id ?? null
            ]);
            
            Log::info("Event created with ID: {$event->id}");
            
            // Commit FIRST to ensure event is saved
            DB::commit();
            
            Log::info("Transaction committed successfully");
            
            // 🔔 NOTIFY ALL TEACHERS about this calendar event (after commit!)
            try {
                $this->notifyTeachersAboutEvent($event);
                Log::info("Notifications sent successfully");
            } catch (\Exception $notifError) {
                Log::error("Notification failed but event was saved: " . $notifError->getMessage());
            }
            
            Log::info("Calendar event committed: {$event->title} ({$event->event_type}) from {$event->start_date} to {$event->end_date}");
            
            // Verify it's actually in the database
            $verification = SchoolCalendarEvent::find($event->id);
            Log::info("Verification - Event exists in DB: " . ($verification ? "YES" : "NO"));
            
            return response()->json([
                'success' => true,
                'message' => 'Calendar event created successfully',
                'event' => $event,
                'notifications_sent' => true
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating calendar event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create calendar event',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update calendar event
     */
    public function update(Request $request, $id)
    {
        try {
            $event = SchoolCalendarEvent::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'event_type' => 'sometimes|required|in:holiday,half_day,early_dismissal,no_classes,school_event,teacher_training,exam_day'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $event->update($request->all());
            
            // Notify teachers about update if dates changed
            if ($request->has('start_date') || $request->has('end_date')) {
                $this->notifyTeachersAboutEvent($event, 'updated');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Calendar event updated successfully',
                'event' => $event
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating calendar event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update calendar event'
            ], 500);
        }
    }
    
    /**
     * Delete calendar event
     */
    public function destroy($id)
    {
        try {
            $event = SchoolCalendarEvent::findOrFail($id);
            $event->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Calendar event deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deleting calendar event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete calendar event'
            ], 500);
        }
    }
    
    /**
     * Notify all teachers about calendar event
     */
    private function notifyTeachersAboutEvent(SchoolCalendarEvent $event, string $action = 'created')
    {
        Log::info("🔔 notifyTeachersAboutEvent called", ['event_id' => $event->id, 'event_title' => $event->title, 'action' => $action]);
        
        try {
            // Get all teachers (no status column in teachers table)
            $teachers = Teacher::all();
            Log::info("Found teachers for notification", ['count' => $teachers->count()]);
            
            $notificationCount = 0;
            
            foreach ($teachers as $teacher) {
                // Create notification title and message
                $icon = $this->getEventIcon($event->event_type);
                $title = $action === 'created' ? '📅 New Calendar Event' : '📅 Calendar Event Updated';
                
                $message = $this->formatEventMessage($event);
                
                // Store notification in database using teacher ID directly
                DB::table('notifications')->insert([
                    'user_id' => $teacher->id, // Use teacher ID directly!
                    'type' => 'calendar_event',
                    'title' => $title,
                    'message' => $message,
                    'data' => json_encode([
                        'event_id' => $event->id,
                        'event_type' => $event->event_type,
                        'start_date' => $event->start_date->format('Y-m-d'),
                        'end_date' => $event->end_date->format('Y-m-d'),
                        'affects_attendance' => $event->affects_attendance,
                        'action' => $action,
                        'teacher_id' => $teacher->id
                    ]),
                    'priority' => $event->affects_attendance ? 'high' : 'normal',
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $notificationCount++;
            }
            
            Log::info("Notified {$notificationCount} teachers about calendar event: {$event->title}");
            
        } catch (\Exception $e) {
            Log::error('Error notifying teachers about event: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Get icon for event type
     */
    private function getEventIcon($eventType): string
    {
        return match($eventType) {
            'holiday' => '🎄',
            'half_day' => '⏰',
            'early_dismissal' => '🏠',
            'no_classes' => '📋',
            'school_event' => '🎉',
            'teacher_training' => '👨‍🏫',
            'exam_day' => '📝',
            default => '📅'
        };
    }
    
    /**
     * Format event message for notification
     */
    private function formatEventMessage(SchoolCalendarEvent $event): string
    {
        $icon = $this->getEventIcon($event->event_type);
        $dateRange = $event->start_date->format('M d') === $event->end_date->format('M d') 
            ? $event->start_date->format('M d, Y')
            : $event->start_date->format('M d') . ' - ' . $event->end_date->format('M d, Y');
        
        $attendanceNote = $event->affects_attendance ? ' (No attendance required)' : '';
        
        return "{$icon} {$event->title} - {$dateRange}{$attendanceNote}";
    }
}
