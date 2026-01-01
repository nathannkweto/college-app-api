<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Course;
use App\Models\Department;
use App\Models\Qualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProgramController extends Controller
{
    /**
     * List Programs with their Dept/Qual info.
     */
    public function index()
    {
        $programs = Program::with(['department', 'qualification'])->get()->map(function ($p) {
            return [
                'public_id' => $p->public_id,
                'name' => $p->name,
                'code' => $p->code,
                'total_semesters' => $p->total_semesters,

                'department' => $p->department ? [
                    'public_id' => $p->department->public_id,
                    'name' => $p->department->name,
                    'code' => $p->department->code,
                ] : null,

                'qualification' => $p->qualification ? [
                    'public_id' => $p->qualification->public_id,
                    'name' => $p->qualification->name,
                    'code' => $p->qualification->code,
                ] : null,
            ];
        });

        return response()->json(['data' => $programs]);
    }

    /**
     * Create a Program.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:programs,code',
            'total_semesters' => 'required|integer|min:1',
            'department_public_id' => 'required|exists:departments,public_id',
            'qualification_public_id' => 'required|exists:qualifications,public_id',
        ]);

        $dept = Department::where('public_id', $request->department_public_id)->first();
        $qual = Qualification::where('public_id', $request->qualification_public_id)->first();

        $program = Program::create([
            'name' => $request->name,
            'code' => $request->code,
            'total_semesters' => $request->total_semesters,
            'department_id' => $dept->id,
            'qualification_id' => $qual->id,
        ]);

        return response()->json(['message' => 'Program created', 'data' => $program], 201);
    }

    /**
     * Get the curriculum (attached courses) for a program.
     */
    public function getCourses($public_id)
    {
        $program = Program::where('public_id', $public_id)->firstOrFail();

        // 1. Get courses with the pivot data (semester_sequence, lecturer_id)
        $courses = $program->courses;

        // 2. Optimization: Fetch all involved lecturers in one query to avoid N+1 issues
        $lecturerIds = $courses->pluck('pivot.lecturer_id')->filter()->unique();
        $lecturers = \App\Models\Lecturer::whereIn('id', $lecturerIds)->get()->keyBy('id');

        // 3. Map the response
        $curriculum = $courses->map(function ($course) use ($lecturers) {
            $lecturer = null;
            if ($course->pivot->lecturer_id && isset($lecturers[$course->pivot->lecturer_id])) {
                $l = $lecturers[$course->pivot->lecturer_id];
                $lecturer = [
                    'public_id' => $l->public_id,
                    'name'      => $l->title . ' ' . $l->first_name . ' ' . $l->last_name,
                ];
            }

            return [
                'public_id' => $course->public_id,
                'name' => $course->name,
                'code' => $course->code,
                'pivot' => [
                    'semester_sequence' => $course->pivot->semester_sequence,
                    'lecturer' => $lecturer, // <--- Now includes the lecturer object!
                ],
            ];
        });

        return response()->json(['data' => $curriculum]);
    }

    /**
     * Attach (or Update) a course in a program.
     */
    public function attachCourse(Request $request, $public_id)
    {
        $request->validate([
            'course_public_id' => 'required|exists:courses,public_id',
            'semester_sequence' => 'required|integer|min:1',
            'lecturer_public_id' => 'nullable|exists:lecturers,public_id' // <--- New Validation
        ]);

        $program = Program::where('public_id', $public_id)->firstOrFail();
        $course = Course::where('public_id', $request->course_public_id)->firstOrFail();

        $lecturerId = null;
        if ($request->filled('lecturer_public_id')) {
            $lecturer = \App\Models\Lecturer::where('public_id', $request->lecturer_public_id)->first();
            $lecturerId = $lecturer ? $lecturer->id : null;
        }

        if ($request->semester_sequence > $program->total_semesters) {
            return response()->json(['message' => 'Semester sequence exceeds program duration.'], 422);
        }

        $program->courses()->syncWithoutDetaching([
            $course->id => [
                'semester_sequence' => $request->semester_sequence,
                'lecturer_id' => $lecturerId
            ]
        ]);

        return response()->json(['message' => 'Course updated in curriculum successfully.']);
    }

    /**
     * Remove a course from a program.
     */
    public function detachCourse($program_public_id, $course_public_id)
    {
        $program = Program::where('public_id', $program_public_id)->firstOrFail();
        $course = Course::where('public_id', $course_public_id)->firstOrFail();

        $program->courses()->detach($course->id);

        return response()->json(['message' => 'Course removed from curriculum.']);
    }
}
