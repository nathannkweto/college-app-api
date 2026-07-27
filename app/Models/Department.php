<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'code',
        'department_number',
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
        static::creating(function (Department $department) {
            if (empty($department->public_id)) {
                $department->public_id = (string) Str::uuid();
            }
        });
    }

    public function programs()
    {
        return $this->hasMany(Program::class, 'department_db_id', 'db_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'department_db_id', 'db_id');
    }

    public function lecturers()
    {
        return $this->hasMany(Lecturer::class, 'department_db_id', 'db_id');
    }
}
