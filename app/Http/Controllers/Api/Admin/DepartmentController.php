<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * List all departments.
     */
    public function index()
    {
        return response()->json([
            'data' => Department::select('public_id', 'name', 'code')->get()
        ]);
    }

    /**
     * Create a new department.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:departments,code',
        ]);

        $department = Department::create($validated);

        return response()->json([
            'message' => 'Department created successfully',
            'data' => [
                'public_id' => $department->public_id,
                'name' => $department->name,
                'code' => $department->code,
            ]
        ], 201);
    }
}
