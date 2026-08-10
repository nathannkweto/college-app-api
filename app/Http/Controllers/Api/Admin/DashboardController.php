<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Level;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/admin/dashboard/metrics
     * Matches admin.yaml -> DashboardMetrics exactly.
     */
    public function metrics(Request $request)
    {
        return response()->json([
                'total_students' => Student::count(),
                'total_lecturers' => Student::count(),
                'total_programs' => Program::count(),
                'levels' => Level::count(),
        ]);
    }

    /**
     * GET /api/v1/admin/dashboard/finance
     * Matches admin.yaml -> DashboardFinance exactly.
     */
     public function finance(Request $request)
    {
        $income = (float) Transaction::where('type', 'income')->sum('amount');
        $expenses = (float) Transaction::where('type', 'expense')->sum('amount');
        $activeSemester = Semester::getActive();

        return response()->json([
            'income' => $income,
            'expenses' => $expenses,
            'net_balance' => $income - $expenses,
            // Spec types this as a plain string (not format: uuid, unlike every
            // other public_id field here) — a human-readable label, not the raw id.
                'active_semester' => $activeSemester
                    ? "Semester {$activeSemester->semester_number} - {$activeSemester->academic_year}"
                    : null,
        ]);
    }
}
