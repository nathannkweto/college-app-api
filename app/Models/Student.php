<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];



    protected $casts = [
        'enrollment_date' => 'date'
    ];

    // 1. Relationships
    public function user() {
            return $this->belongsTo(User::class);
        }
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function fees()
    {
        return $this->hasMany(FinanceFee::class);
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }

    /**
     * 2. LOGIC: Get courses for the student's CURRENT sequence.
     * * If Student is in Sequence 3 (Year 2, Sem 1), fetch only Sequence 3 courses
     * from their Program.
     */
    public function currentCourses()
    {
        // We act as if we are the Program, but filter by the student's level
        return $this->program->courses()
            ->wherePivot('semester_sequence', $this->current_semester_sequence);
    }

    /**
     * 3. LOGIC: Get Failed Courses (Carryovers)
     * Courses from PREVIOUS sequences that do not have a 'passed' exam result.
     */
    public function carryOverCourses()
    {
        // Get all course IDs the student has passed
        $passedCourseIds = $this->examResults()->where('is_passed', true)->pluck('course_id');

        // Get all courses from previous sequences (e.g. if I am seq 3, look at 1 and 2)
        return $this->program->courses()
            ->wherePivot('semester_sequence', '<', $this->current_semester_sequence)
            ->whereNotIn('courses.id', $passedCourseIds);
    }

    /**
     * 4. Helper: Calculate Total Balance
     */
    public function getOutstandingBalanceAttribute()
    {
        return $this->fees()->where('status', '!=', 'cleared')->sum('balance');
    }
}
