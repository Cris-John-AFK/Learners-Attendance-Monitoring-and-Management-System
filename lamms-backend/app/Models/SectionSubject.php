<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionSubject extends Model
{
    use HasFactory;

    protected $table = 'section_subject';

    // Disable timestamps since we're having issues with them
    public $timestamps = false;

    protected $fillable = [
        'section_id',
        'subject_id'
    ];

    /**
     * Get the section that owns the relationship.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the subject that owns the relationship.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}

