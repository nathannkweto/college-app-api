<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Qualification;
use Illuminate\Http\Request;

class QualificationController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Qualification::select('public_id', 'name', 'code')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:qualifications,code',
        ]);

        $qualification = Qualification::create($validated);

        return response()->json([
            'message' => 'Qualification created successfully',
            'data' => $qualification
        ], 201);
    }
}
