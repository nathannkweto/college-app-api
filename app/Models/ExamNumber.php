<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamNumber extends Model
{
    protected $guarded = ['id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function examSeason()
    {
        return $this->belongsTo(ExamSeason::class);
    }
}
