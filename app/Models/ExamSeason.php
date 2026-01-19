<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExamSeason extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];

    protected $fillable = ['name', 'semester_id', 'is_active'];

    public function semester() {
        return $this->belongsTo(Semester::class);
    }

    public function papers() {
        return $this->hasMany(ExamPaper::class);
    }

    public function scopeActive($query)
    {
        // We handle the "String Literal" requirement here, once, forever.
        return $query->where('is_active', 'true');
    }
}
