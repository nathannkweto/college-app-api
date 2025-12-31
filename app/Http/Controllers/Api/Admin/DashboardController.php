<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Semester;
use App\Models\FinanceTransaction;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * GET /dashboard/metrics
     * Returns core counts for the dashboard KPI cards.
     */
    public function metrics(): JsonResponse
    {
        // 1. Calculate Core Counts
        $studentsCount = Student::where('status', 'active')->count();
        $lecturersCount = Lecturer::count();
        $programsCount = Program::count();

        // "Levels" can be interpreted as total defined semesters or academic years
        $levelsCount = Semester::count();

        return response()->json([
            'data' => [
                'students'  => $studentsCount,
                'lecturers' => $lecturersCount,
                'programs'  => $programsCount,
                'levels'    => $levelsCount,
            ]
        ]);
    }

    /**
     * GET /dashboard/finance
     * Returns financial health overview.
     */
    public function finance(): JsonResponse
    {
        // 1. Calculate Financial Totals (All Time)
        $totalIncome = FinanceTransaction::where('type', 'income')->sum('amount');
        $totalExpenses = FinanceTransaction::where('type', 'expense')->sum('amount');

        $netBalance = $totalIncome - $totalExpenses;

        // 2. Get Active Semester Name for display
        $activeSemester = Semester::where('is_active', true)->first();

        $semesterName = $activeSemester
            ? "{$activeSemester->academic_year} (Sem {$activeSemester->semester_number})"
            : "No Active Semester";

        return response()->json([
            'data' => [
                'income'          => (float) $totalIncome,
                'expenses'        => (float) $totalExpenses,
                'net_balance'     => (float) $netBalance,
                'active_semester' => $semesterName,
            ]
        ]);
    }
}
