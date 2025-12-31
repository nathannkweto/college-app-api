<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class FinanceFee extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $fillable = [
        'student_id', 'title', 'total_amount',
        'balance', 'due_date'
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function transactions()
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    /**
     * Balance and status based on transactions.
     * Called whenever a payment is made.
     */
    public function recalculateBalance()
    {
        // Sum all payments made towards this fee
        $paid = $this->transactions()
            ->where('type', 'income')
            ->sum('amount');

        $this->balance = $this->total_amount - $paid;

        if ($this->balance <= 0) {
            $this->balance = 0;
            $this->status = 'cleared';
        } elseif ($paid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'pending';
        }

        $this->save();
    }
}
