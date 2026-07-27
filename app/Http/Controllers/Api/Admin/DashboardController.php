<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Department;
use App\Models\Course;
use App\Models\Semester;
use App\Models\Fee;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/admin/dashboard/metrics
     */
    public function metrics()
    {
        $activeSemester = Semester::where('is_active', true)->first();

        return response()->json([
            'data' => [
                'total_students'     => Student::count(),
                'total_lecturers'    => Lecturer::count(),
                'total_programs'     => Program::count(),
                'total_departments'  => Department::count(),
                'total_courses'      => Course::count(),
                'active_semester'    => $activeSemester ? [
                    'public_id'       => $activeSemester->public_id,
                    'semester_number' => $activeSemester->semester_number,
                    'academic_year'   => $activeSemester->academic_year,
                    'start_date'      => $activeSemester->start_date,
                ] : null,
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/dashboard/finance
     */
    public function finance()
    {
        $totalIncome = Transaction::where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('type', 'expense')->sum('amount');

        $pendingFees = Fee::where('status', 'pending')->count();
        $paidFees = Fee::where('status', 'paid')->count();

        return response()->json([
            'data' => [
                'total_income'     => (float) $totalIncome,
                'total_expense'    => (float) $totalExpense,
                'net_balance'      => (float) ($totalIncome - $totalExpense),
                'pending_fees'     => $pendingFees,
                'paid_fees'        => $paidFees,
                'recent_transactions' => Transaction::orderByDesc('date')
                    ->limit(10)
                    ->get(),
            ]
        ]);
    }
}
