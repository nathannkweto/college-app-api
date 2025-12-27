<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    // Auto-generate Lecturer ID (e.g., MAT-2503)
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lecturer) {
            if (empty($lecturer->lecturer_id)) {
                // 1. Get Dept Code (MAT)
                $dept = Department::find($lecturer->department_id);
                $deptCode = $dept ? $dept->code : 'GEN';

                // 2. Get Employment Year (25)
                $year = date('y', strtotime($lecturer->employment_date));

                // 3. Sequence
                $count = static::where('department_id', $lecturer->department_id)->count() + 1;
                $number = str_pad($count, 2, '0', STR_PAD_LEFT);

                // 4. Combine: MAT-2503
                $lecturer->lecturer_id = $deptCode . '-' . $year . $number;
            }
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
