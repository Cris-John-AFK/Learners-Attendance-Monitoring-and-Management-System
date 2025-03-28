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
        'is_active'
    ];

    protected $with = ['grade'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
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
