<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResultController extends Controller
{
    /**
     * GET /api/v1/student/results
     */
    public function index(Request $request)
    {
        $student = Auth::user()->profile;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $results = Result::with(['course', 'semester.academicYear'])
            ->where('student_db_id', $student->db_id)
            ->orderByDesc('semester_db_id')
            ->get()
            ->groupBy(fn (Result $r) => $r->semester_db_id)
            ->map(function ($group) {
                $semester = $group->first()->semester;

                return [
                    'semester' => $semester ? [
                        'public_id' => $semester->public_id,
                        'semester_number' => $semester->semester_number,
                        'academic_year' => $semester->academicYear?->name,
                    ] : null,
                    'courses' => $group->map(fn (Result $r) => [
                        'course_code' => $r->course?->code,
                        'course_name' => $r->course?->name,
                        'score' => $r->score,
                        'grade' => $r->grade,
                        'passed' => $r->passed,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $results]);
    }
}
