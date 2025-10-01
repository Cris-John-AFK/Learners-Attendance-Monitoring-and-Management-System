<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'student_status_history';

    protected $fillable = [
        'student_id',
        'previous_status',
        'new_status',
        'reason',
        'reason_category',
        'effective_date',
        'changed_by_teacher_id',
        'notes'
    ];

    protected $casts = [
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship: History belongs to a student
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Relationship: History was created by a teacher
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'changed_by_teacher_id');
    }
}
