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
            if ($model->role === 'primary') {
                $model->is_primary = true;
            }
            if ($model->is_primary === true && $model->role !== 'primary') {
                $model->role = 'primary';
            }
        });

        static::updating(function ($model) {
            // Ensure consistency between role and is_primary
            if ($model->role === 'primary') {
                $model->is_primary = true;
            }
            if ($model->is_primary === true && $model->role !== 'primary') {
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
            'is_active' => $this->is_active,
            'role' => $this->role
        ];
    }

    /**
     * Check if this is a primary teacher assignment.
     */
    public function isPrimaryAssignment()
    {
        return $this->is_primary || $this->role === 'primary';
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
        return $query->where(function($q) {
            $q->where('is_primary', true)
              ->orWhere('role', 'primary');
        });
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
                     ->where('role', '!=', 'primary');
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
        return $this->is_primary || $this->role === 'primary';
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
        $wasPrimary = $this->isPrimary();
        $this->is_primary = !$wasPrimary;

        // Update role to match is_primary status
        if (!$wasPrimary) {
            $this->role = 'primary';
        } else {
            $this->role = 'subject';
        }

        $this->save();
        return $this;
    }
}
