<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubjectSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'section_id',
        'subject_id',
        'teacher_id',
        'day',
        'start_time',
        'end_time',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Check for scheduling conflicts.
     *
     * @param int $teacherId
     * @param string $day
     * @param string $startTime
     * @param string $endTime
     * @param int|null $exceptScheduleId
     * @return bool
     */
    public static function hasConflict($teacherId, $day, $startTime, $endTime, $exceptScheduleId = null)
    {
        $query = self::where('teacher_id', $teacherId)
            ->where('day', $day)
            ->where(function ($query) use ($startTime, $endTime) {
                // Check for time overlap
                $query->where(function ($q) use ($startTime, $endTime) {
                    // New schedule starts during an existing schedule
                    $q->where('start_time', '<=', $startTime)
                        ->where('end_time', '>', $startTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // New schedule ends during an existing schedule
                    $q->where('start_time', '<', $endTime)
                        ->where('end_time', '>=', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // New schedule contains an existing schedule
                    $q->where('start_time', '>=', $startTime)
                        ->where('end_time', '<=', $endTime);
                });
            });

        // Exclude the current schedule if updating
        if ($exceptScheduleId) {
            $query->where('id', '!=', $exceptScheduleId);
        }

        return $query->exists();
    }
}
