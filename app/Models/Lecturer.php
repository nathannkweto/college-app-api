<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

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
    public function assignedProgramCourses()
    {
        return $this->hasMany(ProgramCourse::class, 'lecturer_id');
    }
}
