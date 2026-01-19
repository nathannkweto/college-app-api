<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    // Table is 'enrollment' (singular) based on your schema
    protected $table = 'enrollments';

    protected $guarded = ['id'];

    // Cast score to float for easier comparison
    protected $casts = [
        'score' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function programCourse()
    {
        return $this->belongsTo(ProgramCourse::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
