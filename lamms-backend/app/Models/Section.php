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
        'grade_id',
        'description',
        'capacity',
        'is_active'
    ];

    protected $with = ['grade'];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer'
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
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

    /**
     * Scope a query to only include active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if section name is unique within the grade
     */
    public static function isNameUniqueInGrade($name, $gradeId, $excludeSectionId = null)
    {
        $query = static::where('name', $name)
            ->where('grade_id', $gradeId);

        if ($excludeSectionId) {
            $query->where('id', '!=', $excludeSectionId);
        }

        return !$query->exists();
    }
}
