<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function lecturers()
    {
        return $this->hasMany(Lecturer::class);
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }
}
