<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'date',
        'status', // present, absent, late, etc.
        'remarks'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
