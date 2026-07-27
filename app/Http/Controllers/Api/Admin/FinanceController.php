<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    /**
     * POST /api/v1/admin/finance/fees
     */
    public function storeFee(Request $request)
    {
        $validated = $request->validate([
            'student_public_id'    => 'required|exists:students,public_id',
            'status'               => 'required|in:pending,paid',
            'transaction_public_id'=> 'nullable|exists:transactions,public_id',
            // Optional: create a transaction at the same time
            'title'                => 'nullable|string|max:255',
            'amount'               => 'nullable|numeric|min:0',
            'type'                 => 'nullable|in:income,expense',
            'date'                 => 'nullable|date',
            'note'                 => 'nullable|string',
        ]); 

        $student = Student::where('public_id', $validated['student_public_id'])->firstOrFail();

        $transactionId = null;

        // Optionally create a transaction
        if (!empty($validated['title']) && !empty($validated['amount'])) {
            $transaction = Transaction::create([
                'public_id' => (string) Str::uuid(),
                'title'     => $validated['title'],
                'type'      => $validated['type'] ?? 'income',
                'amount'    => $validated['amount'],
                'date'      => $validated['date'] ?? now()->toDateString(),
                'note'      => $validated['note'] ?? null,
            ]);
            $transactionId = $transaction->db_id;
        } elseif (!empty($validated['transaction_public_id'])) {
            $transaction = Transaction::where('public_id', $validated['transaction_public_id'])->firstOrFail();
            $transactionId = $transaction->db_id;
        }

        if (!$transactionId) {
            return response()->json([
                'message' => 'Either provide transaction_public_id or title + amount to create a new transaction'
            ], 422);
        }

        $fee = Fee::create([
            'public_id'         => (string) Str::uuid(),
            'student_db_id'     => $student->db_id,
            'status'            => $validated['status'],
            'transaction_db_id' => $transactionId,
        ]);

        $fee->load(['student', 'transaction']);

        return response()->json([
            'message' => 'Fee record created successfully',
            'data'    => $fee
        ], 201);
    }

    /**
     * GET /api/v1/admin/finance/students/{student_id}/fees
     * Note: {student_id} is the public_id of the student
     */
    public function getStudentFees($studentPublicId)
    {
        $student = Student::where('public_id', $studentPublicId)->firstOrFail();

        $fees = Fee::with('transaction')
            ->where('student_db_id', $student->db_id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $fees
        ]);
    }

    /**
     * GET /api/v1/admin/finance/transactions
     */
    public function indexTransactions(Request $request)
    {
        $query = Transaction::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $transactions = $query->orderByDesc('date')->paginate(20);

        return response()->json($transactions);
    }

    /**
     * POST /api/v1/admin/finance/transactions
     */
    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'date'   => 'required|date',
            'note'   => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'public_id' => (string) Str::uuid(),
            'title'     => $validated['title'],
            'type'      => $validated['type'],
            'amount'    => $validated['amount'],
            'date'      => $validated['date'],
            'note'      => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'data'    => $transaction
        ], 201);
    }
}
