<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Get courses the student is currently studying (based on sequence).
     */
    public function current(Request $request)
    {
        $student = Auth::user()->profile;

        // 1. Find the currently active semester
        // We assume there is only one active semester at a time
        $activeSemester = Semester::where('is_active', 'true')->first();

        // If no semester is running, the student has no active courses
        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        // 2. Query Enrollments
        // We filter by the student AND the active semester.
        // We 'eager load' the course details to avoid "N+1" query performance issues.
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $activeSemester->id)
            ->with('programCourse.course') // Eager load the nested course data
            ->get();

        // 3. Map to the Spec format
        $data = $enrollments->map(function ($enrollment) {
            $course = $enrollment->programCourse->course;

            // Optional: If you still want to label repeats, you can compare
            // $enrollment->programCourse->semester_sequence with the student's current semester.
            // For now, we return exactly what the spec asks for.
            return [
                'code' => $course->code,
                'name' => $course->name,
            ];
        });

        return response()->json([
            'data' => $data
        ]);
    }
}
