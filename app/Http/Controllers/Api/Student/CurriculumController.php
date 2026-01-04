<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user()->profile; // Assuming 'profile' relates to the Student model
        $program = $student->program;

        if (!$program) {
            return response()->json(['data' => null], 404);
        }

        // 1. Fetch courses WITH pivot data
        // We must use 'withPivot' to access semester_sequence
        $allCourses = $program->courses()
            ->withPivot('semester_sequence')
            ->orderByPivot('semester_sequence', 'asc')
            ->get();

        // 2. Group by the Pivot Field
        // We use a callback because the data is nested in ->pivot
        $semesters = $allCourses->groupBy(function ($course) {
            return $course->pivot->semester_sequence;
        })->map(function ($courses, $sequence) use ($student) {

            $seq = (int) $sequence;

            return [
                // Logic: Seq 1,2 = Year 1; Seq 3,4 = Year 2, etc.
                'title' => "Year " . ceil($seq / 2) . " - Semester " . ($seq % 2 == 0 ? 2 : 1),

                // Logic: If student is in seq 3, then 1 and 2 are cleared.
                'is_cleared' => (int) $student->current_semester_sequence > $seq,

                'is_current' => (int) $student->current_semester_sequence == $seq,

                'courses' => $courses->map(function ($c) {
                    return [
                        'code' => $c->code,
                        'name' => $c->name,
                        // API expects 'is_cleared', normally determined by checking exam results
                        // For now, we default to false or could check against a list of passed course IDs
                        'is_cleared' => false,
                    ];
                })->values()
            ];
        })->values(); // Reset keys to array (0, 1, 2...) for JSON

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

        // Count courses in previous semesters using wherePivot
        $approxCoursesDone = $program->courses()
            ->wherePivot('semester_sequence', '<', $student->current_semester_sequence)
            ->count();

        return round($approxCoursesDone / $total, 3);
    }
}
