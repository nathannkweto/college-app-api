<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <--- Don't forget this!
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'public_id', 'profileable_id', 'profileable_type' // <--- Added these
    ];

    protected $hidden = [
        'password', 'remember_token', 'id',
    ];

    // Auto-generate UUID when creating a user
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    // Relationship to Student/Lecturer profile
    public function profile()
    {
        return $this->morphTo('profileable');
    }
}
