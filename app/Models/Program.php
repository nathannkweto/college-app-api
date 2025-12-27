<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    public function qualification()
    {
        return $this->belongsTo(Qualification::class);
    }

    // The Many-to-Many relationship with extra pivot data
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'program_course')
            ->withPivot('semester_sequence'); // Important!
    }

    public function studentGroups()
    {
        return $this->hasMany(StudentGroup::class); // Note: We renamed Group -> StudentGroup
    }
}
