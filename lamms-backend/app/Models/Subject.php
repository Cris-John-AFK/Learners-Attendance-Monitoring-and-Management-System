<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'description',
        'credits',
        'is_active'
    ];

    // Primary key is string
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Get the grades that this subject belongs to.
     */
    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class)
                    ->withTimestamps();
    }
}
