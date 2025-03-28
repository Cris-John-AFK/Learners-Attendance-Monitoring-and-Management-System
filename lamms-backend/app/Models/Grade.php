<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'display_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer'
    ];

    /**
     * Get the subjects for this grade.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)
                    ->withTimestamps();
    }

    // Relationship methods will be implemented when related models are created
}
