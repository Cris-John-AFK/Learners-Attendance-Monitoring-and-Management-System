<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Teacher extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_number',
        'address',
        'date_of_birth',
        'gender',
        'is_head_teacher'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_head_teacher' => 'boolean'
    ];

    protected $appends = ['full_name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherSectionSubject::class);
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getActiveAssignments()
    {
        return $this->assignments()->where('is_active', true)->get();
    }

    public function getPrimaryAssignments()
    {
        return $this->assignments()->where('is_primary', true)->get();
    }

    public function getPrimaryAssignmentAttribute()
    {
        return $this->assignments()
            ->where('is_active', true)
            ->where(function($query) {
                $query->where('is_primary', true)
                    ->orWhere('role', 'primary');
            })
            ->with(['section.grade', 'subject'])
            ->first();
    }

    public function getSubjectAssignmentsAttribute()
    {
        return $this->assignments()
            ->where('is_active', true)
            ->where(function($query) {
                $query->where('is_primary', false)
                    ->where('role', '!=', 'primary');
            })
            ->with(['section.grade', 'subject'])
            ->get();
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'teacher_section_subject')
            ->withPivot('subject_id', 'is_primary', 'is_active', 'role');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_section_subject')
            ->withPivot('section_id', 'is_primary', 'is_active', 'role');
    }

    public function scopeActive($query)
    {
        return $query->whereHas('user', function($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeHeadTeachers($query)
    {
        return $query->where('is_head_teacher', true);
    }

    public function isAssignedToSection($sectionId)
    {
        return $this->assignments()->where('section_id', $sectionId)->exists();
    }

    public function isAssignedToSubject($subjectId)
    {
        return $this->assignments()->where('subject_id', $subjectId)->exists();
    }

    public function isPrimaryTeacherForSection($sectionId)
    {
        return $this->assignments()
            ->where('section_id', $sectionId)
            ->where(function($query) {
                $query->where('is_primary', true)
                    ->orWhere('role', 'primary');
            })
            ->exists();
    }

    /**
     * Get the status of a teacher (active/inactive)
     */
    public function getStatusAttribute()
    {
        return $this->user->is_active ? 'active' : 'inactive';
    }
}
