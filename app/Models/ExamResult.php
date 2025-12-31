<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $guarded = ['id'];

    protected $fillable = [
        'student_id', 'course_id', 'semester_id',
        'score', 'grade', 'status', 'mention',
        'is_published', 'is_passed'
    ];

    protected $casts = [
        'is_passed' => 'boolean',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }
    public function course() {
        return $this->belongsTo(Course::class);
    }
    public function semester() {
        return $this->belongsTo(Semester::class);
    }
}
