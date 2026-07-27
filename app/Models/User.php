<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'email',
        'password',
        'role',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'db_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    protected static function booted()
    {
        static::creating(function (User $user) {
            if (empty($user->public_id)) {
                $user->public_id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_db_id', 'db_id');
    }

    public function lecturer()
    {
        return $this->hasOne(Lecturer::class, 'user_db_id', 'db_id');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_db_id', 'db_id');
    }

    /**
     * Helper to get the correct profile based on role
     */
    public function profile()
    {
        return match (strtolower($this->role ?? '')) {
            'admin'     => $this->admin(),
            'lecturer'  => $this->lecturer(),
            'student'   => $this->student(),
            default     => null,
        };
    }
}
