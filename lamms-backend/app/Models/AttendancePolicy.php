<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_name',
        'scope',
        'scope_id',
        'late_threshold_minutes',
        'absent_threshold_minutes',
        'allow_teacher_override',
        'require_verification',
        'allowed_statuses',
        'effective_from',
        'effective_until',
        'is_active'
    ];

    protected $casts = [
        'allow_teacher_override' => 'boolean',
        'require_verification' => 'boolean',
        'allowed_statuses' => 'array',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean'
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForScope($query, $scope, $scopeId = null)
    {
        $query = $query->where('scope', $scope);
        
        if ($scopeId !== null) {
            $query = $query->where('scope_id', $scopeId);
        }
        
        return $query;
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->whereNull('effective_until')
                          ->orWhere('effective_until', '>=', $date);
                    });
    }

    // Helper methods
    public function isEffectiveOn($date): bool
    {
        $effectiveFrom = $this->effective_from;
        $effectiveUntil = $this->effective_until;

        return $effectiveFrom <= $date && 
               ($effectiveUntil === null || $effectiveUntil >= $date);
    }

    public function isStatusAllowed($statusCode): bool
    {
        return in_array($statusCode, $this->allowed_statuses ?? []);
    }

    public function shouldMarkAsLate($minutesLate): bool
    {
        return $minutesLate >= $this->late_threshold_minutes;
    }

    public function shouldMarkAsAbsent($minutesLate): bool
    {
        return $minutesLate >= $this->absent_threshold_minutes;
    }
}
