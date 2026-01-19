<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];
    protected $fillable = [
        'name', 'code', 'total_semesters',
        'qualification_id', 'department_id',
        'lecturer_id'
    ];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'program_courses')
            ->using(ProgramCourse::class) // <--- ADD THIS LINE
            ->withPivot('semester_sequence', 'lecturer_id')
            ->orderByPivot('semester_sequence');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    public function students() {
        return $this->hasMany(Student::class);
    }
}
