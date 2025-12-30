<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceFee;
use App\Models\FinanceTransaction;
use App\Models\Student;
use App\Models\Program;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /**
     * Display a listing of financial transactions.
     * Route: GET /api/admin/finance/transactions
     */
    public function indexTransactions()
    {
        $transactions = FinanceTransaction::with(['financeFee.student'])
            ->latest('date')
            ->limit(100)
            ->get()
            ->map(function ($t) {
                return [
                    'public_id'      => $t->public_id,
                    'transaction_id' => $t->transaction_id,
                    'type'           => $t->type,
                    'title'          => $t->title,
                    // FORCE CAST TO FLOAT HERE
                    'amount'         => (float) $t->amount,
                    'date'           => $t->date ? $t->date->format('Y-m-d') : null,
                    'fee_public_id'  => $t->financeFee?->public_id,
                    'note'           => $t->note,
                ];
            });

        return response()->json(['data' => $transactions]);
    }

    /**
     * Fetch all fees for a specific student.
     * Route: GET /api/admin/finance/students/{student}/fees
     */
    public function getStudentFees($studentInput)
    {
        // 1. Find the student (supports UUID or Human Readable ID like "ST-2024-001")
        $student = Student::where('student_id', $studentInput)
            ->orWhere('public_id', $studentInput)
            ->firstOrFail();

        // 2. Get fees for this student, ordered by due date
        $fees = FinanceFee::where('student_id', $student->id)
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json($fees);
    }

    /**
     * Create Invoices (Bulk or Single).
     * Route: POST /api/admin/finance/fees
     */
    public function storeFee(Request $request)
    {
        // 1. Validate
        $validated = $request->validate([
            'title' => 'required|string',
            'amount' => 'required|numeric',
            'target_type' => 'required|in:ALL,PROGRAM,STUDENT',
            'target_public_id' => 'nullable|string',
            'due_date' => 'nullable|date'
        ]);

        $studentsToInvoice = [];

        // 2. Determine Targets
        switch ($validated['target_type']) {
            case 'ALL':
                $studentsToInvoice = Student::where('status', 'active')->get();
                break;

            case 'PROGRAM':
                // For Programs, we expect the valid UUID (usually from a dropdown)
                $prog = Program::where('public_id', $validated['target_public_id'])->firstOrFail();

                $studentsToInvoice = Student::where('program_id', $prog->id)
                    ->where('status', 'active')
                    ->get();
                break;

            case 'STUDENT':
                // For Students, we allow Human-Readable ID (e.g., "ST-2024-001")
                $input = trim($validated['target_public_id']);

                // Search by 'student_id' (readable) OR 'public_id' (system uuid)
                $student = Student::where('student_id', $input)
                    ->orWhere('public_id', $input)
                    ->first();

                if (!$student) {
                    return response()->json([
                        'message' => "Student not found with ID: {$input}",
                        'errors' => ['target_public_id' => ["ID '{$input}' does not exist."]]
                    ], 404);
                }

                $studentsToInvoice = [$student];
                break;
        }

        // 3. Create Invoices
        $count = 0;
        foreach ($studentsToInvoice as $student) {
            FinanceFee::create([
                'student_id' => $student->id,
                'title' => $validated['title'],
                'total_amount' => $validated['amount'],
                'balance' => $validated['amount'],
                'due_date' => $validated['due_date'] ?? null,
            ]);
            $count++;
        }

        return response()->json(['message' => "Generated invoices for {$count} students."]);
    }

    /**
     * Record a Payment or Expense.
     * Route: POST /api/admin/finance/transactions
     */
    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'fee_public_id' => 'nullable|exists:finance_fees,public_id',
            'transaction_id' => 'required|string'
        ]);

        $feeId = null;
        if (!empty($validated['fee_public_id'])) {
            // 1. Fetch the Fee AND the Student
            $fee = FinanceFee::with('student')->where('public_id', $validated['fee_public_id'])->first();

            if ($fee) {
                $feeId = $fee->id;

                // 2. OVERRIDE the title to use the readable Student ID
                // This ensures it saves as "Fee Payment - ST-2024-001" regardless of frontend input
                $validated['title'] = "Fee Payment - " . $fee->student->student_id . " " . $fee->title;
            }
        }

        $transaction = FinanceTransaction::create([
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'date' => $validated['date'],
            'transaction_id' => $validated['transaction_id'],
            'finance_fee_id' => $feeId
        ]);

        // Note: Ensure your FinanceTransaction observer calls $fee->recalculateBalance()
        // if $feeId is present, or add that logic here manually if you don't use Observers.

        return response()->json($transaction, 201);
    }
}
