<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $fillable = [
        'transaction_id', 'type', 'title', 'amount',
        'date', 'finance_fee_id', 'note'
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'amount' => 'double',
        'total_amount' => 'double',
        'balance' => 'double',
    ];

    // Automatically trigger balance update on the Fee when a transaction is created
    protected static function booted()
    {
        static::created(function ($transaction) {
            if ($transaction->financeFee) {
                $transaction->financeFee->recalculateBalance();
            }
        });
    }

    public function financeFee(): BelongsTo
    {
        return $this->belongsTo(FinanceFee::class, 'finance_fee_id');
    }
}
