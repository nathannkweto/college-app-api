<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ProgramCourse;
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

        // 1. Get the list of "Passed" ProgramCourse IDs efficiently
        // We fetch IDs where the student has an enrollment that is NOT 'Pending' or 'F'
        $passedProgramCourseIds = Enrollment::where('student_id', $student->id)
            ->whereNotIn('grade', ['Pending', 'F']) // Adjust status strings as needed (e.g. 'Failed')
            ->pluck('program_course_id')
            ->toArray();

        // 2. Fetch all ProgramCourses (The Curriculum)
        // We use the ProgramCourse model so we have access to the specific ID linked in enrollments
        $curriculum = ProgramCourse::where('program_id', $program->id)
            ->with('course') // Eager load the actual course details (name, code)
            ->orderBy('semester_sequence', 'asc')
            ->get();

        // 3. Group by Semester Sequence
        $semestersData = $curriculum->groupBy('semester_sequence')
            ->map(function ($programCourses, $sequence) use ($student, $passedProgramCourseIds) {

                $seq = (int) $sequence;

                // Map the courses for this semester
                $mappedCourses = $programCourses->map(function ($pc) use ($passedProgramCourseIds) {
                    return [
                        'code'       => $pc->course->code,
                        'name'       => $pc->course->name,
                        // Check if this specific ProgramCourse ID exists in our passed list
                        'is_cleared' => in_array($pc->id, $passedProgramCourseIds),
                    ];
                });

                // A semester is "cleared" if every single course inside it is cleared
                // valid if the collection does NOT contain any course where is_cleared is false
                $isSemesterCleared = !$mappedCourses->contains('is_cleared', false);

                return [
                    // Display Title (e.g. Year 1 - Semester 1)
                    'title'      => "Year " . ceil($seq / 2) . " - Semester " . ($seq % 2 == 0 ? 2 : 1),
                    'is_cleared' => $isSemesterCleared,
                    'is_current' => (int) $student->current_semester_sequence === $seq,
                    'courses'    => $mappedCourses->values(),
                ];
            })
            ->values(); // Reset array keys for JSON

        // 4. Calculate Real Progress
        $totalCourses = $curriculum->count();
        $passedCount = count($passedProgramCourseIds);
        // Avoid division by zero
        $percentage = $totalCourses > 0 ? round($passedCount / $totalCourses, 3) : 0.0;

        return response()->json([
            'data' => [
                'program_name'          => $program->name,
                'total_semesters'       => $program->total_semesters ?? 8,
                'completion_percentage' => $percentage,
                'semesters'             => $semestersData
            ]
        ]);
    }
}
