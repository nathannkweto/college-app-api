<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    protected $fillable = [
        'public_id',
        'title',
        'type',
        'amount',
        'date',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
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
        static::creating(function (Transaction $transaction) {
            if (empty($transaction->public_id)) {
                $transaction->public_id = (string) Str::uuid();
            }
        });
    }

    public function fees()
    {
        return $this->hasMany(Fee::class, 'transaction_db_id', 'db_id');
    }
}
