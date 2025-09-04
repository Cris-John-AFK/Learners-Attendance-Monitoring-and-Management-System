<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceModification extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'modified_by_teacher_id',
        'old_values',
        'new_values',
        'modification_type',
        'reason',
        'authorized_by_teacher_id',
        'authorized_at'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'authorized_at' => 'datetime'
    ];

    // Relationships
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function modifiedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'modified_by_teacher_id');
    }

    public function authorizedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'authorized_by_teacher_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('modification_type', $type);
    }

    public function scopeAuthorized($query)
    {
        return $query->whereNotNull('authorized_by_teacher_id');
    }

    public function scopeUnauthorized($query)
    {
        return $query->whereNull('authorized_by_teacher_id');
    }

    // Helper methods
    public function isAuthorized(): bool
    {
        return !is_null($this->authorized_by_teacher_id);
    }

    public function authorize(Teacher $teacher): bool
    {
        if ($this->isAuthorized()) {
            return false;
        }

        $this->update([
            'authorized_by_teacher_id' => $teacher->id,
            'authorized_at' => now()
        ]);

        return true;
    }
}
