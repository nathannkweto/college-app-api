<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExamPaper extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];

    protected $fillable = [
        'exam_season_id', 'course_id', 'date',
        'start_time', 'duration_minutes', 'location', 'program_id'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function course() {
        return $this->belongsTo(Course::class);
    }

    public function examSeason() {
        return $this->belongsTo(ExamSeason::class, 'exam_season_id');
    }
}
