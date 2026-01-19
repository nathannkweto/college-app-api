<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $casts = [
        'enrollment_date' => 'date',
        'dob' => 'date',
    ];

    // 1. Relationships
    public function user() {
            return $this->belongsTo(User::class);
        }
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function fees()
    {
        return $this->hasMany(FinanceFee::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Helper: Calculate Total Balance
     */
    public function getOutstandingBalanceAttribute()
    {
        return $this->fees()->where('status', '!=', 'cleared')->sum('balance');
    }

    public function scopeInClass($query, $programId, $sequence)
    {
        return $query->where('program_id', $programId)
            ->where('current_semester_sequence', $sequence);
    }
}
