<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('department')->get()->map(function($course) {
            return [
                'public_id' => $course->public_id,
                'name' => $course->name,
                'code' => $course->code,

                'department' => $course->department ? [
                    'public_id' => $course->department->public_id,
                    'name' => $course->department->name,
                    'code' => $course->department->code,
                ] : null
            ];
        });

        return response()->json(['data' => $courses]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:courses,code',
            'department_public_id' => 'required|exists:departments,public_id'
        ]);

        // Resolve UUID to ID
        $dept = Department::where('public_id', $request->department_public_id)->first();

        $course = Course::create([
            'name' => $request->name,
            'code' => $request->code,
            'department_id' => $dept->id
        ]);

        return response()->json(['message' => 'Course created', 'data' => $course], 201);
    }
}
