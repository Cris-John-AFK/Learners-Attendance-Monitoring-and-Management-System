<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'credits',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_section_subject')
            ->withPivot('section_id', 'is_primary', 'is_active')
            ->withTimestamps();
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'teacher_section_subject')
            ->withPivot('teacher_id', 'is_primary', 'is_active')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
