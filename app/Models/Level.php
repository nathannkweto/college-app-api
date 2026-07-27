<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Level extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'tag',
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
        static::creating(function (Level $level) {
            if (empty($level->public_id)) {
                $level->public_id = (string) Str::uuid();
            }
        });
    }

    public function programs()
    {
        return $this->hasMany(Program::class, 'level_db_id', 'db_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'level_db_id', 'db_id');
    }
}
