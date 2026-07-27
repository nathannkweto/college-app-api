<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Result extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'semester_db_id',
        'student_db_id',
        'course_db_id',
        'score',
        'grade',
        'pass_mark',
    ];

    protected $casts = [
        'score'     => 'decimal:2',
        'pass_mark' => 'decimal:2',
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
        static::creating(function (Result $result) {
            if (empty($result->public_id)) {
                $result->public_id = (string) Str::uuid();
            }
        });
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_db_id', 'db_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_db_id', 'db_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_db_id', 'db_id');
    }
}
