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

        if (!$student) {
            return response()->json(['data' => ['balance' => 0.0, 'transactions' => []]]);
        }

        // 1. Get total balance
        $outstanding = $student->outstanding_balance;

        // 2. Get fees with their transactions
        $fees = $student->fees()->with('transactions')->get();

        $historyItems = [];

        foreach ($fees as $fee) {
            // A. Add the Fee Creation (The Invoice)
            // We use the fee's creation date or a specific 'posted_at' date if you have one
            $historyItems[] = [
                'transaction_id' => 'invoice_' . $fee->public_id, // Prefix to ensure unique ID
                'date'           => $fee->created_at->format('Y-m-d'),
                'title'          => $fee->title,
                'type'           => 'invoice', // Matches Flutter's Enum for "Debt/Charge"
                'amount'         => (float) $fee->total_amount,
            ];

            // B. Add the Payments (The Transactions)
            foreach ($fee->transactions as $txn) {
                $historyItems[] = [
                    'transaction_id' => $txn->public_id,
                    'date'           => $txn->date ? $txn->date->format('Y-m-d') : null,
                    'title'          => "Payment - " . $fee->title,

                    // Map 'income' to 'payment' for Flutter, everything else stays as is
                    'type'           => $txn->type === 'income' ? 'payment' : 'invoice',
                    'amount'         => (float) $txn->amount,
                ];
            }
        }

        // 3. Sort by Date Descending (Newest first)
        usort($historyItems, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // 4. Return Data
        return response()->json([
            'data' => [
                'balance'      => (float) $outstanding,
                'currency'     => 'ZMW',
                'transactions' => $historyItems
            ]
        ]);
    }
}
