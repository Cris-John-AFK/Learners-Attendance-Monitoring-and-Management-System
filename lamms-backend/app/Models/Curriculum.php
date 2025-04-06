<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Curriculum extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'curricula';

    // Disable timestamps
    public $timestamps = false;

    protected $fillable = [
        'name',
        'start_year',
        'end_year',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_year' => 'integer',
        'end_year' => 'integer'
    ];

    // Accessor for year range (for Vue frontend)
    public function getYearRangeAttribute()
    {
        return [
            'start' => (string) $this->start_year,
            'end' => (string) $this->end_year
        ];
    }

    // Mutator for year range (from Vue frontend)
    public function setYearRangeAttribute($value)
    {
        if (isset($value['start'])) {
            $this->attributes['start_year'] = (int) $value['start'];
        }
        if (isset($value['end'])) {
            $this->attributes['end_year'] = (int) $value['end'];
        }
    }

    // Relationships
    public function grades()
    {
        return $this->belongsToMany(Grade::class, 'curriculum_grade')
                    ->withPivot('display_order');
    }

    public function sections()
    {
        return $this->hasManyThrough(
            Section::class,
            CurriculumGrade::class,
            'curriculum_id',
            'curriculum_grade_id'
        );
    }
}
