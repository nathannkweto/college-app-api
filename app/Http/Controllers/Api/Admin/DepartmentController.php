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

    /**
     * Update an existing department.
     */
    public function update(Request $request, $public_id)
    {
        $department = Department::where('public_id', $public_id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'code' => 'sometimes|required|string|unique:departments,code,' . $department->id,
        ]);

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully',
            'data' => [
                'public_id' => $department->public_id,
                'name' => $department->name,
                'code' => $department->code,
            ]
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy($public_id)
    {
        $department = Department::where('public_id', $public_id)->firstOrFail();
        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully'
        ]);
    }
}
