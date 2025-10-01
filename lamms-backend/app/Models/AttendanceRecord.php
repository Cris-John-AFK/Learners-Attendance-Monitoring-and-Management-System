<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attendance_session_id',
        'student_id',
        'attendance_status_id',
        'marked_by_teacher_id',
        'marked_at',
        'arrival_time',
        'departure_time',
        'remarks',
        'reason_id',
        'reason_notes',
        'marking_method',
        'marked_from_ip',
        'location_data',
        'is_verified',
        'verified_by_teacher_id',
        'verified_at',
        'verification_notes'
    ];

    protected $casts = [
        'marked_at' => 'datetime',
        'arrival_time' => 'datetime:H:i:s',
        'location_data' => 'array',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime'
    ];

    // Relationships
    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    public function attendanceReason(): BelongsTo
    {
        return $this->belongsTo(AttendanceReason::class, 'reason_id');
    }

    public function markedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'marked_by_teacher_id');
    }

    public function verifiedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'verified_by_teacher_id');
    }

    public function modifications(): HasMany
    {
        return $this->hasMany(AttendanceModification::class);
    }

    // Scopes
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('attendance_session_id', $sessionId);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    public function scopeByStatus($query, $statusId)
    {
        return $query->where('attendance_status_id', $statusId);
    }

    public function scopeMarkedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('marked_at', [$startDate, $endDate]);
    }

    // Helper methods
    public function isPresent(): bool
    {
        return $this->attendanceStatus->code === 'P';
    }

    public function isAbsent(): bool
    {
        return $this->attendanceStatus->code === 'A';
    }

    public function isLate(): bool
    {
        return $this->attendanceStatus->code === 'L';
    }

    public function hasBeenModified(): bool
    {
        return $this->modifications()->exists();
    }

    public function canBeVerified(): bool
    {
        return !$this->is_verified;
    }

    public function verify(Teacher $teacher, string $notes = null): bool
    {
        if (!$this->canBeVerified()) {
            return false;
        }

        $this->update([
            'is_verified' => true,
            'verified_by_teacher_id' => $teacher->id,
            'verified_at' => now(),
            'verification_notes' => $notes
        ]);

        return true;
    }
}