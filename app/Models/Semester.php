<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];

    protected $fillable = [
        'academic_year', 'semester_number', 'is_active',
        'start_date', 'length_weeks'
    ];

    protected $casts = [
        'start_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Helper to find the active semester
    public static function active()
    {
        return self::where('is_active', true)->first();
    }

    public function examSeasons()
    {
        return $this->hasMany(ExamSeason::class);
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function scopeActive($query)
    {
        // We handle the "String Literal" requirement here, once, forever.
        return $query->where('is_active', 'true');
    }
}
