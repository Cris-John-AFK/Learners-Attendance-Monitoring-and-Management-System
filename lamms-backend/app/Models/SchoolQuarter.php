<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolQuarter extends Model
{
    use HasFactory;

    protected $table = 'school_quarters';

    protected $fillable = [
        'school_year',
        'quarter',
        'start_date',
        'end_date',
        'description'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get teachers who have access to this quarter
     */
    public function teachers()
    {
        return $this->belongsToMany(
            Teacher::class,
            'quarter_teacher_access',
            'quarter_id',
            'teacher_id'
        )->withTimestamps();
    }
}
