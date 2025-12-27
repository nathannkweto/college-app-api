<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('department');

        if ($request->has('department_public_id')) {
            $deptId = Department::getIdFromPublicId($request->department_public_id);
            $query->where('department_id', $deptId);
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:courses,code',
            'department_public_id' => 'required|exists:departments,public_id',
        ]);

        $deptId = Department::getIdFromPublicId($validated['department_public_id']);

        $course = Course::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'department_id' => $deptId,
        ]);

        return response()->json($course, 201);
    }
}
