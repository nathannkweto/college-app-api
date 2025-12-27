<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Semester;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary()
    {
        $activeSemester = Semester::with('academicYear')->where('is_active', true)->first();

        // Calculate Daily Income (Income today)
        $dailyIncome = Transaction::where('type', 'income')
            ->whereDate('date', now())
            ->sum('amount');

        return response()->json([
            'students_count' => Student::where('status', 'active')->count(),
            'lecturers_count' => Lecturer::where('status', 'active')->count(),
            'active_semester' => $activeSemester ? $activeSemester->academicYear->year . ' (Sem ' . $activeSemester->semester_number . ')' : 'None',
            'daily_income' => (float) $dailyIncome
        ]);
    }
}
