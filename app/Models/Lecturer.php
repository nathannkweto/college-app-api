<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];
    protected $fillable = [
        'lecturer_id', 'user_id', 'first_name', 'last_name',
        'email', 'title', 'gender', 'department_id', 'national_id',
        'dob', 'address', 'phone',
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'timetable_entries', 'lecturer_id', 'course_id')
            ->withPivot(['semester_id'])
            ->distinct();
    }
}
