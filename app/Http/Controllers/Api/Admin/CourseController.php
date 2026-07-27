<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $courses = $query->orderBy('name')->get();

        return response()->json([
            'data' => $courses
        ]);
    }

    /**
     * POST /api/v1/admin/courses
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'department_public_id' => 'required|exists:departments,public_id',
            'code'                 => 'nullable|integer',
        ]);

        $department = Department::where('public_id', $validated['department_public_id'])->firstOrFail();

        $course = Course::create([
            'public_id'        => (string) Str::uuid(),
            'name'             => $validated['name'],
            'department_db_id' => $department->db_id,
            'code'             => $validated['code'] ?? 0,
        ]);

        $course->load('department');

        return response()->json([
            'message' => 'Course created successfully',
            'data'    => $course
        ], 201);
    }
}
