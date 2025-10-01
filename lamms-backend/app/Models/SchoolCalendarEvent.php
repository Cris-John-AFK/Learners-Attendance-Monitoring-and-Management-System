<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SchoolCalendarEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'event_type',
        'affects_attendance',
        'modified_start_time',
        'modified_end_time',
        'affected_sections',
        'affected_grade_levels',
        'is_recurring',
        'recurrence_pattern',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'modified_start_time' => 'datetime',
        'modified_end_time' => 'datetime',
        'affected_sections' => 'array',
        'affected_grade_levels' => 'array',
        'affects_attendance' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Check if a specific date is a school day (has classes)
     */
    public static function isSchoolDay(Carbon $date, ?int $sectionId = null, ?int $gradeLevel = null): bool
    {
        $events = self::where('is_active', true)
            ->where(function($query) use ($date) {
                $query->whereDate('start_date', '<=', $date)
                      ->whereDate('end_date', '>=', $date);
            })
            ->whereIn('event_type', ['holiday', 'no_classes', 'teacher_training'])
            ->where('affects_attendance', true)
            ->get();

        foreach ($events as $event) {
            // Check if event affects this specific section/grade
            if ($event->affected_sections && $sectionId) {
                if (!in_array($sectionId, $event->affected_sections)) {
                    continue;
                }
            }
            
            if ($event->affected_grade_levels && $gradeLevel) {
                if (!in_array($gradeLevel, $event->affected_grade_levels)) {
                    continue;
                }
            }

            // Event applies to this date and section/grade - NOT a school day!
            return false;
        }

        return true; // It's a school day
    }

    /**
     * Get event for a specific date
     */
    public static function getEventForDate(Carbon $date, ?int $sectionId = null): ?self
    {
        return self::where('is_active', true)
            ->where(function($query) use ($date) {
                $query->whereDate('start_date', '<=', $date)
                      ->whereDate('end_date', '>=', $date);
            })
            ->where(function($query) use ($sectionId) {
                if ($sectionId) {
                    $query->whereNull('affected_sections')
                          ->orWhereJsonContains('affected_sections', $sectionId);
                }
            })
            ->first();
    }

    /**
     * Check if it's a half-day
     */
    public static function isHalfDay(Carbon $date, ?int $sectionId = null): bool
    {
        $event = self::getEventForDate($date, $sectionId);
        return $event && in_array($event->event_type, ['half_day', 'early_dismissal']);
    }
}
