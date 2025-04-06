<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherSectionSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'teacher_section_subject';

    // Ensure Laravel knows there are timestamps in this table
    public $timestamps = true;

    protected $fillable = [
        'teacher_id',
        'section_id',
        'subject_id', // Can be NULL for homeroom teachers
        'is_primary',
        'is_active',
        'role'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Ensure consistency between role and is_primary
            if ($model->role === 'primary' || $model->role === 'homeroom') {
                $model->is_primary = true;
            }

            // For homeroom, allow NULL subject_id
            if ($model->role === 'homeroom') {
                $model->subject_id = null;
            }

            // If this is marked as primary and role isn't set, set the role
            if ($model->is_primary === true && $model->role !== 'primary' && $model->role !== 'homeroom') {
                $model->role = 'primary';
            }
        });

        static::updating(function ($model) {
            // Ensure consistency between role and is_primary
            if ($model->role === 'primary' || $model->role === 'homeroom') {
                $model->is_primary = true;
            }

            // For homeroom, allow NULL subject_id
            if ($model->role === 'homeroom') {
                $model->subject_id = null;
            }

            // If this is marked as primary and role isn't set, set the role
            if ($model->is_primary === true && $model->role !== 'primary' && $model->role !== 'homeroom') {
                $model->role = 'primary';
            }
        });
    }

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
        return $this->belongsTo(Subject::class)->withDefault(['name' => 'Homeroom']);
    }

    /**
     * Get the assignment details.
     */
    public function getAssignmentDetailsAttribute()
    {
        return [
            'teacher' => $this->teacher->full_name,
            'section' => $this->section->name,
            'subject' => $this->subject ? $this->subject->name : 'Homeroom',
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'role' => $this->role
        ];
    }

    /**
     * Check if this is a primary teacher assignment.
     */
    public function isPrimaryAssignment()
    {
        return $this->is_primary || $this->role === 'primary' || $this->role === 'homeroom';
    }

    /**
     * Scope to get homeroom teachers
     */
    public function scopeHomeroom($query)
    {
        return $query->where('role', 'homeroom');
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive assignments.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include primary assignments.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include secondary assignments.
     */
    public function scopeSecondary($query)
    {
        return $query->where('is_primary', false);
    }

    /**
     * Scope to get assignments by role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get subject-only assignments (non-primary)
     */
    public function scopeSubjectsOnly($query)
    {
        return $query->where('is_primary', false)
                     ->where('role', '!=', 'primary')
                     ->where('role', '!=', 'homeroom');
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
        return $this->is_primary || $this->role === 'primary' || $this->role === 'homeroom';
    }

    /**
     * Check if this is a homeroom teacher assignment.
     */
    public function isHomeroom()
    {
        return $this->role === 'homeroom';
    }

    public function schedules()
    {
        return $this->hasMany(SubjectSchedule::class);
    }

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
        // Don't toggle primary status for homeroom teachers - they're always primary
        if ($this->role === 'homeroom') {
            return $this;
        }

        $this->is_primary = !$this->is_primary;

        // Ensure role is set to 'primary' if is_primary is true
        if ($this->is_primary) {
            $this->role = 'primary';
        } else {
            $this->role = 'subject';
        }

        $this->save();
        return $this;
    }

    public function setAsPrimary()
    {
        // First demote any existing primary assignments for this section/subject
        if ($this->section_id && $this->subject_id) {
            self::where('section_id', $this->section_id)
                ->where('subject_id', $this->subject_id)
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false, 'role' => 'subject']);
        }

        // Now set this one as primary
        $this->is_primary = true;
        $this->role = 'primary';
        $this->save();

        return $this;
    }

    /**
     * Set this assignment as a homeroom teacher
     */
    public function setAsHomeroom()
    {
        // Remove any existing homeroom teachers for this section
        self::where('section_id', $this->section_id)
            ->where('role', 'homeroom')
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false, 'role' => 'subject']);

        // Set this one as the homeroom teacher
        $this->is_primary = true;
        $this->role = 'homeroom';
        $this->subject_id = null;
        $this->save();

        // Also update the section's homeroom_teacher_id
        $section = Section::find($this->section_id);
        if ($section) {
            $section->homeroom_teacher_id = $this->teacher_id;
            $section->save();
        }

        return $this;
    }
}
