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
        'program_id', // <--- THIS IS MISSING
        'course_id',
        'lecturer_id',
        'day',
        'start_time',
        'end_time',
        'location',
    ];

    public function semester() {
        return $this->belongsTo(Semester::class);
    }

    public function course() {
        return $this->belongsTo(Course::class);
    }

    public function lecturer() {
        return $this->belongsTo(Lecturer::class);
    }

    public function program() {
        return $this->belongsTo(Program::class);
    }
}
