<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QualificationController extends Controller
{
    /**
     * GET /api/v1/admin/qualifications
     */
    public function index()
    {
        $levels = Level::orderBy('name')->get();

        return response()->json([
            'data' => $levels
        ]);
    }

    /**
     * POST /api/v1/admin/qualifications
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:levels,name',
            'tag'  => 'required|string|max:20|unique:levels,tag',
        ]);

        $level = Level::create([
            'public_id' => (string) Str::uuid(),
            'name'      => $validated['name'],
            'tag'       => strtoupper($validated['tag']),
        ]);

        return response()->json([
            'message' => 'Qualification created successfully',
            'data'    => $level
        ], 201);
    }
}
