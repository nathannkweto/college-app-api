<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Level;
use App\Models\Department;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    /**
     * GET /api/v1/admin/programs
     */
    public function index(Request $request)
    {
        $query = Program::with(['level', 'department']);

        if ($request->filled('department_public_id')) {
            $department = Department::where('public_id', $request->department_public_id)->first();
            if ($department) {
                $query->where('department_db_id', $department->db_id);
            }
        }

        if ($request->filled('level_public_id')) {
            $level = Level::where('public_id', $request->level_public_id)->first();
            if ($level) {
                $query->where('level_db_id', $level->db_id);
            }
        }

        $programs = $query->orderBy('name')->get();

        return response()->json([
            'data' => $programs
        ]);
    }

    /**
     * POST /api/v1/admin/programs
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'tag'                  => 'required|string|max:20',
            'program_number'       => 'required|integer',
            'level_public_id'      => 'required|exists:levels,public_id',
            'department_public_id' => 'required|exists:departments,public_id',
        ]);

        $level = Level::where('public_id', $validated['level_public_id'])->firstOrFail();
        $department = Department::where('public_id', $validated['department_public_id'])->firstOrFail();

        $program = Program::create([
            'public_id'        => (string) Str::uuid(),
            'name'             => $validated['name'],
            'tag'              => strtoupper($validated['tag']),
            'program_number'   => $validated['program_number'],
            'level_db_id'      => $level->db_id,
            'department_db_id' => $department->db_id,
        ]);

        $program->load(['level', 'department']);

        return response()->json([
            'message' => 'Program created successfully',
            'data'    => $program
        ], 201);
    }

    /**
     * GET /api/v1/admin/programs/{public_id}/courses
     * Note: Currently there is no program_courses pivot table.
     * This returns courses belonging to the same department as a temporary solution.
     */
    public function getCourses($publicId)
    {
        $program = Program::with('department')->where('public_id', $publicId)->firstOrFail();

        $courses = Course::where('department_db_id', $program->department_db_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $courses
        ]);
    }

    /**
     * POST /api/v1/admin/programs/{public_id}/courses
     * Note: No pivot table exists yet. This is a placeholder.
     */
    public function attachCourse(Request $request, $publicId)
    {
        return response()->json([
            'message' => 'Attach course endpoint - requires program_courses table (not yet implemented)'
        ], 501);
    }

    /**
     * DELETE /api/v1/admin/programs/{public_id}/courses/{course_public_id}
     * Note: No pivot table exists yet. This is a placeholder.
     */
    public function detachCourse($publicId, $coursePublicId)
    {
        return response()->json([
            'message' => 'Detach course endpoint - requires program_courses table (not yet implemented)'
        ], 501);
    }
}
