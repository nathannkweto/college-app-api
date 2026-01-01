<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProgramCourse extends Pivot
{
    // Explicitly define table name since it follows the pivot convention
    protected $table = 'program_course';

    // Since your migration has $table->id(), we enable incrementing
    public $incrementing = true;

    // Relationships to the parents
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Relationship to the Exam Paper
    // One "Course in a Program" can have many exam papers (usually 1 per season)
    public function examPapers()
    {
        return $this->hasMany(ExamPaper::class, 'program_course_id');
    }
}
