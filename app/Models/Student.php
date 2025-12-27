<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    // Auto-generate the Student ID (e.g., 25BSC032) on creation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->student_id)) {
                // 1. Get Year (25)
                $year = date('y');

                // 2. Get Qualification Code (e.g., BSC)
                // We need to fetch the program -> qualification relationship
                $program = Program::with('qualification')->find($student->program_id);
                $qualCode = $program ? $program->qualification->code : 'GEN'; // Fallback

                // 3. Get Next Sequence Number
                // Count existing students in this program for this year + 1
                $count = static::where('program_id', $student->program_id)
                        ->whereYear('created_at', date('Y'))
                        ->count() + 1;
                $number = str_pad($count, 3, '0', STR_PAD_LEFT);

                // 4. Combine: 25BSC032
                $student->student_id = $year . $qualCode . $number;
            }
        });
    }

    public function user()
    {
        return $this->morphOne(User::class, 'profileable');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
