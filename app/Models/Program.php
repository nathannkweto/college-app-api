<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Program extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'tag',
        'program_number',
        'level_db_id',
        'department_db_id',
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
        static::creating(function (Program $program) {
            if (empty($program->public_id)) {
                $program->public_id = (string) Str::uuid();
            }
        });
    }

    public function level()
    {
        return $this->belongsTo(Level::class, 'level_db_id', 'db_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_db_id', 'db_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'program_db_id', 'db_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class, 'program_db_id', 'db_id');
    }
}
