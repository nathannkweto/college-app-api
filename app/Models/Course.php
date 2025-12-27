<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_course')
            ->withPivot('semester_sequence');
    }
}
