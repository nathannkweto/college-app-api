<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CurriculumController extends Controller
{
    /**
     * SIMPLIFIED vs. the original design: there is no program_courses/
     * curriculum table in this schema, so we can't group by
     * "semester_sequence" (1st/2nd/3rd semester of study) or know which
     * courses are officially required by the program. This instead lists
     * every course in the program's department and marks it cleared based
     * on the student's results. If you need real curriculum sequencing
     * later, that needs a dedicated table — this is a department-wide
     * approximation, consistent with how courses relate to programs
     * elsewhere in this schema.
     */
    public function index(Request $request)
    {
        $student = Auth::user()->profile()->with('program.department')->first();

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $program = $student->program;

        if (!$program) {
            return response()->json(['data' => null], 404);
        }

        $courses = $program->department->courses;

        $passedCourseDbIds = Result::where('student_db_id', $student->db_id)
            ->whereColumn('score', '>=', 'pass_mark')
            ->pluck('course_db_id')
            ->unique();

        $mappedCourses = $courses->map(fn ($course) => [
            'public_id' => $course->public_id,
            'code' => $course->code,
            'name' => $course->name,
            'is_cleared' => $passedCourseDbIds->contains($course->db_id),
        ]);

        $totalCourses = $courses->count();
        $passedCount = $mappedCourses->where('is_cleared', true)->count();
        $percentage = $totalCourses > 0 ? round($passedCount / $totalCourses, 3) : 0.0;

        return response()->json([
            'data' => [
                'program_name' => $program->name,
                'department' => $program->department->name,
                'completion_percentage' => $percentage,
                'total_courses' => $totalCourses,
                'passed_courses' => $passedCount,
                'courses' => $mappedCourses->values(),
            ],
        ]);
    }
}
