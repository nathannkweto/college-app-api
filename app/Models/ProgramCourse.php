<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProgramCourse extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'program_db_id',
        'course_db_id',
        'semester_sequence',
        'lecturer_db_id',
    ];

    protected static function booted()
    {
        static::creating(function (ProgramCourse $pc) {
            if (empty($pc->public_id)) {
                $pc->public_id = (string) Str::uuid();
            }
        });
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_db_id', 'db_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_db_id', 'db_id');
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_db_id', 'db_id');
    }
}
