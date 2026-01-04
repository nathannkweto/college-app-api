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
     */
    public function getStudentFees($studentInput)
    {
        // 1. Find the student (supports UUID or Human Readable ID like "ST-2024-001")
        $student = Student::where('student_id', $studentInput)
            ->orWhere('public_id', $studentInput)
            ->firstOrFail();

        // 2. Get fees for this student, ordered by due date
        $fees = FinanceFee::with('student.program') // Eager load to be safe
        ->where('student_id', $student->id)
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($fee) {
                return [
                    'public_id'    => $fee->public_id,
                    // The Spec expects a 'student' object inside
                    'student'      => [
                        'public_id'  => $fee->student->public_id,
                        'student_id' => $fee->student->student_id,
                        'first_name' => $fee->student->first_name,
                        'last_name'  => $fee->student->last_name,
                        'email'      => $fee->student->email,
                        'program'    => [
                            'name' => $fee->student->program->name ?? 'N/A',
                            'code' => $fee->student->program->code ?? '',
                        ]
                    ],
                    'title'        => $fee->title,
                    'total_amount' => (float) $fee->total_amount, // Cast to ensure double
                    'balance'      => (float) $fee->balance,
                    'status'       => $fee->balance <= 0 ? 'cleared' : ($fee->balance < $fee->total_amount ? 'partial' : 'pending'),
                    'due_date'     => $fee->due_date ? $fee->due_date->format('Y-m-d') : null,
                ];
            });

        // 3. IMPORTANT: Wrap in 'data' to match OpenAPI "FinanceFeesGet200Response"
        return response()->json(['data' => $fees]);
    }

    /**
     * Create Invoices (Bulk or Single).
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
                $prog = Program::where('public_id', $validated['target_public_id'])->firstOrFail();

                $studentsToInvoice = Student::where('program_id', $prog->id)
                    ->where('status', 'active')
                    ->get();
                break;

            case 'STUDENT':
                $input = trim($validated['target_public_id']);

                // Search by 'student_id' OR 'public_id'
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

        return response()->json($transaction, 201);
    }
}
