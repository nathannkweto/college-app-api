<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * List courses assigned to the logged-in lecturer for the active semester,
     * derived from their timetable entries (no program_courses table exists).
     */
    public function index(Request $request)
    {
        $lecturer = Auth::user()->profile;

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        $activeSemester = Semester::getActive();

        if (!$activeSemester) {
            return response()->json(['message' => 'No active semester found.'], 404);
        }

        $entries = TimetableEntry::with(['course', 'program'])
            ->where('semester_db_id', $activeSemester->db_id)
            ->where('lecturer_db_id', $lecturer->db_id)
            ->get()
            ->unique(function ($entry) {
                return $entry->course_db_id . '-' . $entry->program_db_id;
            });

        $data = $entries->map(fn (TimetableEntry $entry) => [
            'public_id' => $entry->course?->public_id,
            'course_name' => $entry->course?->name,
            'course_code' => $entry->course?->code,
            'program_public_id' => $entry->program?->public_id,
            'program_name' => $entry->program?->name,
        ])->values();

        return response()->json([
            'meta' => [
                'semester_number' => $activeSemester->semester_number,
                'academic_year' => $activeSemester->academicYear?->name,
            ],
            'data' => $data,
        ]);
    }

    /**
     * Course detail + roster.
     *
     * NOTE: there is no enrollment/registration table in this schema, so
     * "students in this class" is approximated as all students whose
     * program matches the timetable entry's program. This can overshoot
     * (includes students who haven't actually taken the course) until a
     * real enrollment table exists.
     */
    public function show(Request $request, string $publicId)
    {
        $lecturer = Auth::user()->profile;

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        $activeSemester = Semester::getActive();
        if (!$activeSemester) {
            abort(404, 'No active semester found.');
        }

        $course = Course::where('public_id', $publicId)->firstOrFail();

        $entry = TimetableEntry::with('program')
            ->where('lecturer_db_id', $lecturer->db_id)
            ->where('course_db_id', $course->db_id)
            ->where('semester_db_id', $activeSemester->db_id)
            ->firstOrFail();

        $students = $entry->program
            ? Student::where('program_db_id', $entry->program->db_id)->orderBy('last_name')->get()
            : collect();

        $results = Result::where('course_db_id', $course->db_id)
            ->where('semester_db_id', $activeSemester->db_id)
            ->whereIn('student_db_id', $students->pluck('db_id'))
            ->get()
            ->keyBy('student_db_id');

        return response()->json([
            'course' => [
                'public_id' => $course->public_id,
                'name' => $course->name,
                'code' => $course->code,
            ],
            'program' => $entry->program ? [
                'public_id' => $entry->program->public_id,
                'name' => $entry->program->name,
            ] : null,
            'context' => [
                'semester_number' => $activeSemester->semester_number,
                'student_count' => $students->count(),
            ],
            'students' => $students->map(function (Student $student) use ($results) {
                $result = $results->get($student->db_id);

                return [
                    'public_id' => $student->public_id,
                    'student_id' => $student->id, // business ID, e.g. "2025-BA-001"
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'current_score' => $result?->score,
                    'current_grade' => $result?->grade ?? 'Pending',
                ];
            })->values(),
        ]);
    }
}
