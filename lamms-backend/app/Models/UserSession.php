<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'role',
        'ip_address',
        'user_agent',
        'last_activity',
        'expires_at',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if session is expired
     */
    public function isExpired()
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return now()->greaterThan($this->expires_at);
    }

    /**
     * Check if session is active (within last 30 minutes)
     */
    public function isActive()
    {
        return now()->diffInMinutes($this->last_activity) < 30;
    }
}
