<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExamSeason extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];

    protected $fillable = ['name', 'semester_id'];

    public function semester() {
        return $this->belongsTo(Semester::class);
    }

    public function papers() {
        return $this->hasMany(ExamPaper::class);
    }
}
