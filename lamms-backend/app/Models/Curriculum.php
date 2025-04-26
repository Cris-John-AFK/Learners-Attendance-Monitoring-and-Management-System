<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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
        'is_active',
        'status',
        'description'
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

    /**
     * Get all sections in this curriculum
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Get all subjects in this curriculum through curriculum_subject_section
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'curriculum_grade_subject')
            ->withPivot(['grade_id', 'units', 'hours_per_week', 'description'])
            ->withTimestamps();
    }

    /**
     * Get all grades that have sections in this curriculum
     */
    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'curriculum_grade')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active curriculums.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'Active');
    }

    /**
     * Activate this curriculum and deactivate all others
     */
    public function activate()
    {
        // Deactivate all other curriculums
        self::where('id', '!=', $this->id)->update(['is_active' => false]);

        // Activate this curriculum
        $this->update([
            'is_active' => true,
            'status' => 'Active'
        ]);
    }

    public function deactivate()
    {
        $this->update([
            'is_active' => false,
            'status' => 'Archived'
        ]);
    }

    public function getSubjectsByGrade($gradeId)
    {
        return $this->subjects()
            ->wherePivot('grade_id', $gradeId)
            ->get();
    }
}
