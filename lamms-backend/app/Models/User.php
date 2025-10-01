<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'force_password_reset',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'is_active' => 'boolean',
        'force_password_reset' => 'boolean',
    ];

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    public function guardhouseUser()
    {
        return $this->hasOne(GuardhouseUser::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTeacher()
    {
        return $this->role === 'teacher';
    }

    public function isGuardhouse()
    {
        return $this->role === 'guardhouse';
    }

    /**
     * Get the profile for the user based on their role
     */
    public function getProfile()
    {
        return match($this->role) {
            'admin' => $this->admin,
            'teacher' => $this->teacher,
            'guardhouse' => $this->guardhouseUser,
            default => null,
        };
    }
}
