<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TimetableEntry extends Model
{
    use HasPublicId, SoftDeletes;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'semester_db_id',
        'program_db_id',
        'course_db_id',
        'lecturer_db_id',
        'day',
        'start_time',
        'end_time',
        'location',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
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
        static::creating(function (TimetableEntry $entry) {
            if (empty($entry->public_id)) {
                $entry->public_id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_db_id', 'db_id');
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
