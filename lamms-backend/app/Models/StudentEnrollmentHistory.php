<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollmentHistory extends Model
{
    use HasFactory;

    protected $table = 'student_enrollment_history';

    protected $fillable = [
        'student_id',
        'section_id',
        'enrolled_date',
        'unenrolled_date',
        'enrollment_status',
        'school_year',
        'notes'
    ];

    protected $casts = [
        'enrolled_date' => 'date',
        'unenrolled_date' => 'date'
    ];

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('enrollment_status', 'active');
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForSection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeForSchoolYear($query, $schoolYear)
    {
        return $query->where('school_year', $schoolYear);
    }

    public function scopeEnrolledOn($query, $date)
    {
        return $query->where('enrolled_date', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->whereNull('unenrolled_date')
                          ->orWhere('unenrolled_date', '>=', $date);
                    });
    }

    // Helper methods
    public function isActiveOn($date): bool
    {
        return $this->enrolled_date <= $date && 
               ($this->unenrolled_date === null || $this->unenrolled_date >= $date) &&
               $this->enrollment_status === 'active';
    }

    public function getDurationInDays(): ?int
    {
        if ($this->unenrolled_date) {
            return $this->enrolled_date->diffInDays($this->unenrolled_date);
        }
        return $this->enrolled_date->diffInDays(now());
    }
}
