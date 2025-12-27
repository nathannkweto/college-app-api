<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i', // Format output if needed
    ];

    public function examSeason()
    {
        return $this->belongsTo(ExamSeason::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function examGroups()
    {
        return $this->hasMany(ExamGroup::class);
    }
}
