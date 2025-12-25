<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str; // Required for UUID generation

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',              // Custom
        'public_id',         // Custom
        'profileable_id',    // Custom (Polymorphic)
        'profileable_type',  // Custom (Polymorphic)
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'id', // Hide internal ID, expose public_id instead
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Automatically hashes passwords on save
        ];
    }

    /**
     * The "booted" method of the model.
     * This replaces the database default value.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Automatically generate a UUID if one isn't provided
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationship to the specific profile (Student, Lecturer, etc.)
     */
    public function profile()
    {
        return $this->morphTo('profileable');
    }
}
