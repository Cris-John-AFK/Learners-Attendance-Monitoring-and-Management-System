<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'date',
        'time_in',
        'status', // Keep for backward compatibility
        'attendance_status_id',
        'remarks',
        'marked_at'
    ];

    protected $casts = [
        'date' => 'date',
        'time_in' => 'datetime',
        'marked_at' => 'datetime'
    ];

    /**
     * Get the student that owns the attendance
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the section that owns the attendance
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the subject that owns the attendance
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher that marked the attendance
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the attendance status
     */
    public function attendanceStatus()
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    /**
     * Get attendance for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Get attendance for a specific section
     */
    public function scopeForSection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    /**
     * Get attendance for a specific subject
     */
    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    /**
     * Get attendance marked by a specific teacher
     */
    public function scopeMarkedBy($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
