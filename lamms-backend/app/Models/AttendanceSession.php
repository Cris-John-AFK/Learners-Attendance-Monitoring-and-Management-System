<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'section_id',
        'subject_id',
        'session_date',
        'session_start_time',
        'session_end_time',
        'session_type',
        'status',
        'metadata',
        'completed_at'
    ];

    protected $casts = [
        'session_date' => 'date',
        'session_start_time' => 'datetime:H:i:s',
        'session_end_time' => 'datetime:H:i:s',
        'metadata' => 'array',
        'completed_at' => 'datetime'
    ];

    // Relationships
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('session_date', $date);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForSection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canBeModified(): bool
    {
        return in_array($this->status, ['active', 'completed']);
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->session_start_time && $this->session_end_time) {
            $start = $this->session_start_time;
            $end = $this->session_end_time;
            return $start->diffInMinutes($end);
        }
        return null;
    }
}