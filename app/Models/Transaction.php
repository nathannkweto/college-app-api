<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_fee_payment' => 'boolean',
    ];

    /**
     * Optional link to a specific Fee (if this was a fee payment)
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }
}
