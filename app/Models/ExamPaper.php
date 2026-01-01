<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExamPaper extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];

    protected $fillable = [
        'exam_season_id',
        'program_course_id',
        'date',
        'start_time',
        'duration_minutes',
        'location'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function programCourse()
    {
        return $this->belongsTo(ProgramCourse::class, 'program_course_id');
    }

    public function examSeason() {
        return $this->belongsTo(ExamSeason::class, 'exam_season_id');
    }
    public function getCourseAttribute()
    {
        return $this->programCourse->course ?? null;
    }
}
