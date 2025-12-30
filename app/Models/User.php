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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'public_id', // Make sure this is added!
        'name',
        'email',
        'password',
        'role',
        'profileable_id',
        'profileable_type'
    ];

    /**
     * Get Specific Profile
     */
    public function profile()
    {
        $role = strtoupper($this->role ?? '');

        if ($role === 'STUDENT') {
            return $this->hasOne(Student::class, 'user_id');
        } elseif ($role === 'LECTURER') {
            return $this->hasOne(Lecturer::class, 'user_id');
        } elseif ($role === 'ADMIN') {
            return $this->hasOne(Admin::class, 'user_id');
        }

        return $this->hasOne(Student::class, 'user_id')->whereRaw('1 = 0');
    }

}
