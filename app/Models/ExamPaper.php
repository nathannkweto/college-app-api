<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamPaper extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'exam_season_db_id',
        'program_db_id',
        'course_db_id',
        'date',
        'start_time',
        'duration_minutes',
        'location',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $hidden = [
        'db_id',
    ];

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    protected static function booted()
    {
        static::creating(function (ExamPaper $paper) {
            if (empty($paper->public_id)) {
                $paper->public_id = (string) Str::uuid();
            }
        });
    }

    public function examSeason()
    {
        return $this->belongsTo(ExamSeason::class, 'exam_season_db_id', 'db_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_db_id', 'db_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_db_id', 'db_id');
    }
}
