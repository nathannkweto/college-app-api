<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user()->profile;
        $program = $student->program;

        if (!$program) {
            return response()->json(['data' => null], 404);
        }

        // 1. Fetch all courses for the program, ordered by sequence
        $allCourses = $program->courses()
            ->orderBy('semester_sequence')
            ->get();

        // 2. Group and Transform into the "semesters" array defined in YAML
        $semesters = $allCourses->groupBy('semester_sequence')->map(function ($courses, $sequence) use ($student) {
            $seq = (int) $sequence;

            return [
                'title' => "Year " . ceil($seq / 2) . " - Semester " . ($seq % 2 == 0 ? 2 : 1),
                'is_cleared' => (int) $student->current_semester_sequence > $seq,
                'is_current' => (int) $student->current_semester_sequence == $seq,
                'courses' => $courses->map(function ($c) {
                    return [
                        'code' => $c->code,
                        'name' => $c->name,
                        'is_cleared' => false,
                    ];
                })->values()
            ];
        })->values();

        // 3. Wrap in the 'data' key
        return response()->json([
            'data' => [
                'program_name' => $program->name,
                'total_semesters' => $program->total_semesters ?? 8,
                'completion_percentage' => $this->calculateProgress($student, $program),
                'semesters' => $semesters
            ]
        ]);
    }

    /**
     * Helper to calculate completion (e.g. 0.375)
     */
    private function calculateProgress($student, $program) {
        $total = $program->courses()->count();
        if ($total == 0) return 0.0;

        $completedSemesters = $student->current_semester_sequence - 1;
        $approxCoursesDone = $program->courses()->where('semester_sequence', '<', $student->current_semester_sequence)->count();

        return round($approxCoursesDone / $total, 3);
    }
}
