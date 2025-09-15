<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_level',
        'section',
        'school_id',
        'school_year',
        'month',
        'total_students',
        'present_today',
        'absent_today',
        'attendance_rate',
        'teacher_name',
    ];

    protected $casts = [
        'total_students' => 'integer',
        'present_today' => 'integer',
        'absent_today' => 'integer',
        'attendance_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
