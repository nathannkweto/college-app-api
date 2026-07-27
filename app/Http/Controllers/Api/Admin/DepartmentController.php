<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    /**
     * GET /api/v1/admin/departments
     */
    public function index()
    {
        $departments = Department::orderBy('name')->get();

        return response()->json([
            'data' => $departments
        ]);
    }

    /**
     * POST /api/v1/admin/departments
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255|unique:departments,name',
            'code'              => 'required|string|max:20|unique:departments,code',
            'department_number' => 'required|integer|unique:departments,department_number',
        ]);

        $department = Department::create([
            'public_id'         => (string) Str::uuid(),
            'name'              => $validated['name'],
            'code'              => strtoupper($validated['code']),
            'department_number' => $validated['department_number'],
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'data'    => $department
        ], 201);
    }
}
