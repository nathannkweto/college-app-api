<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * GET /api/v1/admin/courses
     */
    public function index(Request $request)
    {
        $query = Course::with('department');

        if ($request->filled('department_public_id')) {
            $department = Department::where('public_id', $request->department_public_id)->first();
            if ($department) {
                $query->where('department_db_id', $department->db_id);
            }
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $courses = $query->orderBy('name')->get();

        return response()->json([
            'data' => $courses->map(fn (Course $c) => $this->format($c)),
        ]);
    }

    /**
     * POST /api/v1/admin/courses
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'department_public_id' => 'required|string|exists:departments,public_id',
        ]);

        $department = Department::where('public_id', $validated['department_public_id'])->firstOrFail();

        $course = Course::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'department_db_id' => $department->db_id,
        ]);

        return response()->json([
            'message' => 'Course created successfully',
            'data' => $this->format($course->load('department')),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/courses/{public_id}
     */
    public function update(Request $request, string $public_id)
    {
        $course = Course::where('public_id', $public_id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255',
            'department_public_id' => 'sometimes|required|string|exists:departments,public_id',
        ]);

        if (isset($validated['department_public_id'])) {
            $department = Department::where('public_id', $validated['department_public_id'])->firstOrFail();
            $validated['department_db_id'] = $department->db_id;
            unset($validated['department_public_id']);
        }

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully',
            'data' => $this->format($course->fresh('department')),
        ]);
    }

    /**
    * DELETE /api/v1/admin/courses/{public_id}
    */
    public function destroy(string $public_id)
    {
        $course = Course::where('public_id', $public_id)->firstOrFail();

        // Block deletion if the course is already in use
        if ($course->results()->exists() || $course->timetableEntries()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this course because it has related results or timetable entries.',
            ], 409);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }

    private function format(Course $course): array
    {
        return [
            'public_id' => $course->public_id,
            'name' => $course->name,
            'code' => $course->code,
            'department' => $course->department ? [
                'public_id' => $course->department->public_id,
                'name' => $course->department->name,
                'code' => $course->department->code,
            ] : null,
        ];
    }
}
