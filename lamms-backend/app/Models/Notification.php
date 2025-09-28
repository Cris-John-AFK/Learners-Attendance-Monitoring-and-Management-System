<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Student;
use App\Models\User;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'priority',
        'is_read',
        'read_at',
        'related_student_id',
        'created_by_user_id'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    /**
     * Notification types
     */
    const TYPE_STATUS_CHANGE = 'status_change';
    const TYPE_ATTENDANCE_ALERT = 'attendance_alert';
    const TYPE_NOTE_REMINDER = 'note_reminder';
    const TYPE_SYSTEM = 'system';
    const TYPE_SESSION_COMPLETED = 'session_completed';

    /**
     * Priority levels
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    /**
     * Get all notification types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_STATUS_CHANGE => 'Status Change',
            self::TYPE_ATTENDANCE_ALERT => 'Attendance Alert',
            self::TYPE_NOTE_REMINDER => 'Note Reminder',
            self::TYPE_SYSTEM => 'System Notification'
        ];
    }

    /**
     * Get all priority levels
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_CRITICAL => 'Critical'
        ];
    }

    /**
     * Get priority colors for UI
     */
    public static function getPriorityColors(): array
    {
        return [
            self::PRIORITY_LOW => 'text-gray-600',
            self::PRIORITY_MEDIUM => 'text-blue-600',
            self::PRIORITY_HIGH => 'text-orange-600',
            self::PRIORITY_CRITICAL => 'text-red-600'
        ];
    }

    /**
     * Relationship: User who receives the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Student related to the notification
     */
    public function relatedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'related_student_id');
    }

    /**
     * Relationship: User who created the notification
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope: Get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope: Get read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope: Get notifications by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get notifications by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Get high priority notifications (high + critical)
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    /**
     * Scope: Get notifications for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get recent notifications (last 30 days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
    }

    /**
     * Get the priority color class for UI
     */
    public function getPriorityColorAttribute(): string
    {
        $colors = self::getPriorityColors();
        return $colors[$this->priority] ?? $colors[self::PRIORITY_MEDIUM];
    }

    /**
     * Get formatted time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if notification is critical
     */
    public function isCritical(): bool
    {
        return $this->priority === self::PRIORITY_CRITICAL;
    }

    /**
     * Check if notification is high priority
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    /**
     * Static method to create a status change notification
     */
    public static function createStatusChangeNotification(
        int $userId,
        int $studentId,
        string $oldStatus,
        string $newStatus,
        int $changedByUserId
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_STATUS_CHANGE,
            'title' => 'Student Status Changed',
            'message' => "Student status changed from {$oldStatus} to {$newStatus}",
            'data' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'student_id' => $studentId
            ],
            'priority' => self::PRIORITY_HIGH,
            'related_student_id' => $studentId,
            'created_by_user_id' => $changedByUserId
        ]);
    }

    /**
     * Static method to create an attendance alert notification
     */
    public static function createAttendanceAlert(
        int $userId,
        int $studentId,
        string $alertType,
        array $data = []
    ): self {
        $priority = $alertType === 'critical' ? self::PRIORITY_CRITICAL : self::PRIORITY_HIGH;
        
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_ATTENDANCE_ALERT,
            'title' => 'Attendance Alert',
            'message' => "Student requires attention: {$alertType}",
            'data' => $data,
            'priority' => $priority,
            'related_student_id' => $studentId
        ]);
    }
}
