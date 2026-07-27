<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Lecturer extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'first_name',
        'last_name',
        'email',
        'gender',
        'title',
        'phone',
        'id',                   // e.g. LEC-IT-001
        'department_db_id',
        'employment_date',
        'user_db_id',
    ];

    protected $casts = [
        'employment_date' => 'date',
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
        static::creating(function (Lecturer $lecturer) {
            if (empty($lecturer->public_id)) {
                $lecturer->public_id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_db_id', 'db_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_db_id', 'db_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class, 'lecturer_db_id', 'db_id');
    }
}
