<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Semester extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'academic_year',
        'academic_year_db_id',
        'semester_number',
        'is_active',
        'start_date',
        'length_weeks',
    ];

    protected $casts = [
        'start_date' => 'date',
        'is_active'  => 'boolean',
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
        static::creating(function (Semester $semester) {
            if (empty($semester->public_id)) {
                $semester->public_id = (string) Str::uuid();
            }
        });
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_db_id', 'db_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class, 'semester_db_id', 'db_id');
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'semester_db_id', 'db_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }
}
