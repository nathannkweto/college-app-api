<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\Student;
use App\Models\Program;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    // Apply Fees (Bulk Action)
    public function storeFee(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'amount' => 'required|numeric',
            'target_type' => 'required|in:ALL,PROGRAM,STUDENT',
            'target_public_id' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $students = collect();

            // Determine Target Audience
            if ($validated['target_type'] === 'STUDENT') {
                $students = Student::where('public_id', $validated['target_public_id'])->get();
            } elseif ($validated['target_type'] === 'PROGRAM') {
                $progId = Program::getIdFromPublicId($validated['target_public_id']);
                $students = Student::where('program_id', $progId)->where('status', 'active')->get();
            } elseif ($validated['target_type'] === 'ALL') {
                $students = Student::where('status', 'active')->get();
            }

            // Create Fee Records
            foreach ($students as $student) {
                Fee::create([
                    'student_id' => $student->id,
                    'title' => $validated['title'],
                    'amount' => $validated['amount'],
                ]);
            }
        });

        return response()->json(['message' => 'Fees applied successfully'], 201);
    }

    // Record Payment
    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'student_public_id' => 'required|exists:students,public_id',
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'transaction_id' => 'required|string|unique:transactions,transaction_id',
            'note' => 'nullable|string'
        ]);

        $student = Student::where('public_id', $validated['student_public_id'])->firstOrFail();

        $transaction = Transaction::create([
            // In a real system, we might link this to a specific Fee ID,
            // but for general ledger, we often just link to the student via a pivot or just store it.
            // Since our schema links Transaction -> Fee (nullable), we leave it null for general credit
            'fee_id' => null,
            'transaction_id' => $validated['transaction_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'is_fee_payment' => true,
            'date' => $validated['date'],
            'note' => $validated['note'] . " (Student: {$student->student_id})"
        ]);

        return response()->json($transaction, 201);
    }

    public function indexTransactions()
    {
        return Transaction::latest()->paginate(20);
    }
}
