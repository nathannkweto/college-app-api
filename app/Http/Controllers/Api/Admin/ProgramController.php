<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Department;
use App\Models\Qualification;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\ProgramCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    /**
     * GET /api/v1/admin/programs
     */
    public function index(Request $request)
    {
        $query = Program::with(['qualification', 'department']);

        if ($request->filled('department_public_id')) {
            $department = Department::where('public_id', $request->department_public_id)->first();
            if ($department) {
                $query->where('department_db_id', $department->db_id);
            }
        }

        if ($request->filled('qualification_public_id')) {
            $qualification = Qualification::where('public_id', $request->qualification_public_id)->first();
            if ($qualification) {
                $query->where('qualification_db_id', $qualification->db_id);
            }
        }

        $programs = $query->orderBy('name')->get();

        return response()->json([
            'data' => $programs->map(fn (Program $p) => $this->format($p)),
        ]);
    }

    /**
     * POST /api/v1/admin/programs
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'code'                    => 'required|string|max:20',
            'qualification_public_id' => 'required|exists:qualifications,public_id',
            'department_public_id'    => 'required|exists:departments,public_id',
            'total_semesters'         => 'required|integer|min:1|max:20',
        ]);

        $qualification = Qualification::where('public_id', $validated['qualification_public_id'])->firstOrFail();
        $department    = Department::where('public_id', $validated['department_public_id'])->firstOrFail();

        $program = Program::create([
            'public_id'           => (string) Str::uuid(),
            'name'                => $validated['name'],
            'code'                => strtoupper($validated['code']),
            'tag'                 => strtoupper($validated['code']), // keep old column in sync
            'total_semesters'     => $validated['total_semesters'],
            'qualification_db_id' => $qualification->db_id,
            'department_db_id'    => $department->db_id,
            'program_number'      => (Program::max('program_number') ?? 0) + 1,
        ]);

        return response()->json([
            'message' => 'Program created successfully',
            'data'    => $this->format($program->load(['qualification', 'department'])),
        ], 201);
    }

    /**
     * GET /api/v1/admin/programs/{public_id}/courses
     */
    public function getCourses(string $public_id)
    {
        $program = Program::where('public_id', $public_id)->firstOrFail();

        $items = ProgramCourse::with(['course.department', 'lecturer'])
            ->where('program_db_id', $program->db_id)
            ->orderBy('semester_sequence')
            ->get();

        return response()->json([
            'data' => $items->map(fn (ProgramCourse $pc) => $this->formatProgramCourse($pc)),
        ]);
    }

    /**
     * POST /api/v1/admin/programs/{public_id}/courses
     */
    public function attachCourse(Request $request, string $public_id)
    {
        $program = Program::where('public_id', $public_id)->firstOrFail();

        $validated = $request->validate([
            'course_public_id'   => 'required|exists:courses,public_id',
            'semester_sequence'  => 'required|integer|min:1',
            'lecturer_public_id' => 'nullable|exists:lecturers,public_id',
        ]);

        $course = Course::where('public_id', $validated['course_public_id'])->firstOrFail();

        $lecturerDbId = null;
        if (!empty($validated['lecturer_public_id'])) {
            $lecturer = Lecturer::where('public_id', $validated['lecturer_public_id'])->firstOrFail();
            $lecturerDbId = $lecturer->db_id;
        }

        $pc = ProgramCourse::updateOrCreate(
            [
                'program_db_id' => $program->db_id,
                'course_db_id'  => $course->db_id,
            ],
            [
                'public_id'         => (string) Str::uuid(),
                'semester_sequence' => $validated['semester_sequence'],
                'lecturer_db_id'    => $lecturerDbId,
            ]
        );

        return response()->json([
            'message' => 'Course linked to program successfully',
            'data'    => $this->formatProgramCourse($pc->load(['course.department', 'lecturer'])),
        ]);
    }

    /**
     * DELETE /api/v1/admin/programs/{public_id}/courses/{course_public_id}
     */
    public function detachCourse(string $public_id, string $course_public_id)
    {
        $program = Program::where('public_id', $public_id)->firstOrFail();
        $course  = Course::where('public_id', $course_public_id)->firstOrFail();

        $deleted = ProgramCourse::where('program_db_id', $program->db_id)
            ->where('course_db_id', $course->db_id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Course is not linked to this program'], 404);
        }

        return response()->json([
            'message' => 'Course removed from program successfully',
        ]);
    }

    private function format(Program $program): array
    {
        return [
            'public_id'       => $program->public_id,
            'name'            => $program->name,
            'code'            => $program->code ?? $program->tag,
            'total_semesters' => $program->total_semesters ?? 6,
            'qualification'   => $program->qualification ? [
                'public_id' => $program->qualification->public_id,
                'name'      => $program->qualification->name,
                'code'      => $program->qualification->code,
            ] : null,
            'department' => $program->department ? [
                'public_id' => $program->department->public_id,
                'name'      => $program->department->name,
                'code'      => $program->department->code,
            ] : null,
        ];
    }

    private function formatProgramCourse(ProgramCourse $pc): array
    {
        $course = $pc->course;

        return [
            'public_id'  => $course?->public_id,
            'name'       => $course?->name,
            'code'       => $course?->code,
            'department' => $course?->department ? [
                'public_id' => $course->department->public_id,
                'name'      => $course->department->name,
                'code'      => $course->department->code,
            ] : null,
            'pivot' => [
                'semester_sequence' => $pc->semester_sequence,
                'lecturer' => $pc->lecturer ? [
                    'public_id' => $pc->lecturer->public_id,
                    'name'      => trim($pc->lecturer->title . ' ' . $pc->lecturer->first_name . ' ' . $pc->lecturer->last_name),
                ] : null,
            ],
        ];
    }
}
