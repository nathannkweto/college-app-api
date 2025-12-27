<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    protected $guarded = ['id'];

    // No Public ID trait needed usually, unless you expose individual slots to API

    public function course() { return $this->belongsTo(Course::class); }
    public function lecturer() { return $this->belongsTo(Lecturer::class); }
    public function group() { return $this->belongsTo(StudentGroup::class, 'student_group_id'); }
}
