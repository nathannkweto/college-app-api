<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return Department::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name',
            'code' => 'required|string|max:5|unique:departments,code',
        ]);

        $dept = Department::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
        ]);

        return response()->json($dept, 201);
    }
}
