<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user()->profile;

        // 1. Get total balance
        $outstanding = $student->outstanding_balance;

        // 2. Get transactions
        $fees = $student->fees()->with('transactions')->get();

        $transactions = [];
        foreach ($fees as $fee) {
            foreach ($fee->transactions as $txn) {
                $transactions[] = [
                    'transaction_id' => $txn->public_id,
                    'date'           => $txn->transaction_date->format('Y-m-d'),
                    'title'          => $fee->title, // Or $txn->description
                    'type'           => $txn->type, // MUST be 'invoice' or 'payment' (lowercase)
                    'amount'         => (float) $txn->amount,
                ];
            }
        }

        // 3. Wrap in the 'data' key
        return response()->json([
            'data' => [
                'balance'      => (float) $outstanding,
                'currency'     => 'ZMW',
                'transactions' => $transactions
            ]
        ]);
    }
}
