<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumGrade extends Model
{
    use HasFactory;

    protected $table = 'curriculum_grade';

    // Disable timestamps
    public $timestamps = false;

    protected $fillable = [
        'curriculum_id',
        'grade_id',
        'display_order'
    ];

    protected $with = ['grade'];

    /**
     * Get the curriculum that owns this grade.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * Get the grade that belongs to this curriculum.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Get the sections for this curriculum grade.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
