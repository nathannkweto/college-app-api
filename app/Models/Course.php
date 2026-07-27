<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'department_db_id',
        'code',
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
        static::creating(function (Course $course) {
            if (empty($course->public_id)) {
                $course->public_id = (string) Str::uuid();
            }
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_db_id', 'db_id');
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'course_db_id', 'db_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class, 'course_db_id', 'db_id');
    }
}
