<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * GET /api/v1/student/courses/current
     * Derived from timetable entries for the student's program in the
     * active semester (no enrollment table — see CurriculumController
     * note for the same caveat: this is program-based, not per-student).
     */
    public function current(Request $request)
    {
        $student = Auth::user()->profile;

        if (!$student || !$student->program_db_id) {
            return response()->json(['data' => []]);
        }

        $activeSemester = Semester::getActive();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        $courses = TimetableEntry::with('course')
            ->where('semester_db_id', $activeSemester->db_id)
            ->where('program_db_id', $student->program_db_id)
            ->get()
            ->pluck('course')
            ->filter()
            ->unique('db_id')
            ->values();

        return response()->json([
            'data' => $courses->map(fn ($course) => [
                'public_id' => $course->public_id,
                'code' => $course->code,
                'name' => $course->name,
            ]),
        ]);
    }
}
