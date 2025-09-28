<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Student;
use App\Models\User;

class StudentStatusChange extends Model
{
    use HasFactory;

    protected $table = 'student_status_changes';

    protected $fillable = [
        'student_id',
        'changed_by_user_id',
        'previous_status',
        'new_status',
        'reason_category',
        'reason_note',
        'effective_date',
        'is_current',
        'changed_at'
    ];

    protected $casts = [
        'effective_date' => 'date',
        'changed_at' => 'datetime',
        'is_current' => 'boolean'
    ];

    /**
     * Status constants for validation
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_DROPPED_OUT = 'dropped_out';
    const STATUS_TRANSFERRED_OUT = 'transferred_out';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_MEDICAL_LEAVE = 'medical_leave';

    /**
     * Reason category constants
     */
    const REASON_SUSPENDED = 'suspended';
    const REASON_MEDICAL = 'medical_reasons';
    const REASON_MOVING = 'moving_away';
    const REASON_OTHERS = 'others';

    /**
     * Get all available statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_DROPPED_OUT => 'Dropped Out',
            self::STATUS_TRANSFERRED_OUT => 'Transferred Out',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_MEDICAL_LEAVE => 'Medical Leave'
        ];
    }

    /**
     * Get all available reason categories
     */
    public static function getReasonCategories(): array
    {
        return [
            self::REASON_SUSPENDED => 'Suspended',
            self::REASON_MEDICAL => 'Medical Reasons',
            self::REASON_MOVING => 'Moving Away',
            self::REASON_OTHERS => 'Others'
        ];
    }

    /**
     * Relationship: Student who had status changed
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Relationship: User who made the change (teacher/admin)
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /**
     * Scope: Get current status for a student
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: Get status changes by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('new_status', $status);
    }

    /**
     * Scope: Get status changes within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }

    /**
     * Get formatted reason for display
     */
    public function getFormattedReasonAttribute(): string
    {
        $categories = self::getReasonCategories();
        $categoryName = $categories[$this->reason_category] ?? $this->reason_category;
        
        if ($this->reason_category === self::REASON_OTHERS && $this->reason_note) {
            return $categoryName . ': ' . $this->reason_note;
        }
        
        return $categoryName;
    }

    /**
     * Check if status change is reversible
     */
    public function isReversible(): bool
    {
        return in_array($this->new_status, [
            self::STATUS_SUSPENDED,
            self::STATUS_MEDICAL_LEAVE
        ]);
    }

    /**
     * Check if status requires archiving
     */
    public function requiresArchiving(): bool
    {
        return in_array($this->new_status, [
            self::STATUS_DROPPED_OUT,
            self::STATUS_TRANSFERRED_OUT
        ]);
    }
}
