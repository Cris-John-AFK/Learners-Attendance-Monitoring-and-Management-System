<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherSectionSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'teacher_section_subject';

    protected $fillable = [
        'teacher_id',
        'section_id',
        'subject_id',
        'is_primary',
        'is_active'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Get the teacher that owns the assignment.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the section that owns the assignment.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the subject that owns the assignment.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the assignment details.
     */
    public function getAssignmentDetailsAttribute()
    {
        return [
            'teacher' => $this->teacher->full_name,
            'section' => $this->section->name,
            'subject' => $this->subject->name,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active
        ];
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include primary assignments.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Check if the assignment is active.
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Check if the assignment is primary.
     */
    public function isPrimary()
    {
        return $this->is_primary;
    }

    /**
     * Toggle the active status of the assignment.
     */
    public function toggleActive()
    {
        $this->is_active = !$this->is_active;
        $this->save();
        return $this;
    }

    /**
     * Toggle the primary status of the assignment.
     */
    public function togglePrimary()
    {
        $this->is_primary = !$this->is_primary;
        $this->save();
        return $this;
    }
}
