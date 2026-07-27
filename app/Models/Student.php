<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'first_name',
        'last_name',
        'national_id_number',
        'gender',
        'email',
        'dob',
        'address',
        'id',                   // Student ID e.g. 2025-BA-001
        'phone',
        'level_db_id',
        'program_db_id',
        'enrollment_date',
        'user_db_id',
    ];

    protected $casts = [
        'dob'             => 'date',
        'enrollment_date' => 'date',
    ];

    protected $hidden = [
        'db_id',
    ];

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    protected static function booted()
    {
        static::creating(function (Student $student) {
            if (empty($student->public_id)) {
                $student->public_id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_db_id', 'db_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_db_id', 'db_id');
    }

    public function level()
    {
        return $this->belongsTo(Level::class, 'level_db_id', 'db_id');
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'student_db_id', 'db_id');
    }

    public function fees()
    {
        return $this->hasMany(Fee::class, 'student_db_id', 'db_id');
    }

    public function transcript()
    {
        return $this->hasOne(Transcript::class, 'student_db_id', 'db_id');
    }
}
