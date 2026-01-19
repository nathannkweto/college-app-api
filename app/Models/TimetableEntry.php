<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];

    protected $fillable = [
        'semester_id',
        'program_course_id',
        'day',
        'start_time',
        'end_time',
        'location',
        'public_id',
    ];

    public function semester() {
        return $this->belongsTo(Semester::class);
    }

    public function programCourse() {
        return $this->belongsTo(ProgramCourse::class, 'program_course_id');
    }

    public function lecturer() {
        return $this->hasOneThrough(
            Lecturer::class,
            ProgramCourse::class,
            'id', // FK on program_courses (id) ... wait, actually it's the target key
            'id', // FK on lecturers (id)
            'program_course_id', // Local key on timetable_entries
            'lecturer_id' // Local key on program_courses
        );
    }

    // HELPER: Get Course info via the pivot
    public function course() {
        return $this->hasOneThrough(
            Course::class,
            ProgramCourse::class,
            'id', // Foreign key on program_courses table
            'id', // Foreign key on courses table
            'program_course_id', // Local key on timetable_entries table
            'course_id' // Local key on program_courses table
        );
    }

    // HELPER: Get Program info via the pivot
    public function program() {
        return $this->hasOneThrough(
            Program::class,
            ProgramCourse::class,
            'id',
            'id',
            'program_course_id',
            'program_id'
        );
    }
}
