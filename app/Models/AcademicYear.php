<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AcademicYear extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
        static::creating(function (AcademicYear $year) {
            if (empty($year->public_id)) {
                $year->public_id = (string) Str::uuid();
            }
        });
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class, 'academic_year_db_id', 'db_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
