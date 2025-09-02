<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'period_type',
        'room_number',
        'notes',
        'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_active' => 'boolean'
    ];

    /**
     * Get the section that owns the schedule
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the subject for this schedule
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher for this schedule
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Scope for homeroom periods
     */
    public function scopeHomeroom($query)
    {
        return $query->where('period_type', 'homeroom');
    }

    /**
     * Scope for subject periods
     */
    public function scopeSubject($query)
    {
        return $query->where('period_type', 'subject');
    }

    /**
     * Scope for active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific day
     */
    public function scopeForDay($query, $day)
    {
        return $query->where('day_of_week', $day);
    }

    /**
     * Get duration in minutes
     */
    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        return $end->diffInMinutes($start);
    }

    /**
     * Check if schedule overlaps with another time period
     */
    public function overlaps($startTime, $endTime, $dayOfWeek = null)
    {
        $query = static::where('section_id', $this->section_id)
            ->where('id', '!=', $this->id ?? 0)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($subQ) use ($startTime, $endTime) {
                    // New schedule starts during existing schedule
                    $subQ->where('start_time', '<=', $startTime)
                         ->where('end_time', '>', $startTime);
                })->orWhere(function ($subQ) use ($startTime, $endTime) {
                    // New schedule ends during existing schedule
                    $subQ->where('start_time', '<', $endTime)
                         ->where('end_time', '>=', $endTime);
                })->orWhere(function ($subQ) use ($startTime, $endTime) {
                    // New schedule completely contains existing schedule
                    $subQ->where('start_time', '>=', $startTime)
                         ->where('end_time', '<=', $endTime);
                });
            });

        if ($dayOfWeek) {
            $query->where('day_of_week', $dayOfWeek);
        } else {
            $query->where('day_of_week', $this->day_of_week);
        }

        return $query->exists();
    }
}
