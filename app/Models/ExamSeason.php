<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSeason extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function schedules()
    {
        return $this->hasMany(ExamSchedule::class);
    }
}
