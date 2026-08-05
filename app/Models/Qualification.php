<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Qualification extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'name',
        'code',
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
        static::creating(function (Qualification $qualification) {
            if (empty($qualification->public_id)) {
                $qualification->public_id = (string) Str::uuid();
            }
        });
    }

    public function programs()
    {
        return $this->hasMany(Program::class, 'qualification_db_id', 'db_id');
    }
}
