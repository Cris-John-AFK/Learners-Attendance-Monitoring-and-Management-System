<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'curriculum_grade_id',
        'homeroom_teacher_id',
        'description',
        'capacity',
        'is_active'
    ];

    protected $with = ['curriculumGrade', 'homeroomTeacher'];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer'
    ];

    public function curriculumGrade()
    {
        return $this->belongsTo(CurriculumGrade::class);
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_section_subject')
            ->withPivot('subject_id', 'is_primary', 'is_active')
            ->withTimestamps();
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_section_subject')
            ->withPivot('teacher_id', 'is_primary', 'is_active')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(SubjectSchedule::class);
    }

    /**
     * Scope a query to only include active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if section name is unique within the curriculum grade
     */
    public static function isNameUniqueInCurriculumGrade($name, $curriculumGradeId, $excludeSectionId = null)
    {
        $query = static::where('name', $name)
            ->where('curriculum_grade_id', $curriculumGradeId);

        if ($excludeSectionId) {
            $query->where('id', '!=', $excludeSectionId);
        }

        return !$query->exists();
    }

    /**
     * Get the grade level through the curriculum_grade relationship
     */
    public function grade()
    {
        return $this->hasOneThrough(
            Grade::class,
            CurriculumGrade::class,
            'id', // Foreign key on curriculum_grade table
            'id', // Foreign key on grades table
            'curriculum_grade_id', // Local key on sections table
            'grade_id' // Local key on curriculum_grade table
        );
    }
}
