<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Fee extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'student_db_id',
        'status',
        'transaction_db_id',
    ];

    protected $hidden = [
        'db_id',
    ];

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    protected static function booted()
    {
        static::creating(function (Fee $fee) {
            if (empty($fee->public_id)) {
                $fee->public_id = (string) Str::uuid();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_db_id', 'db_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_db_id', 'db_id');
    }
}
