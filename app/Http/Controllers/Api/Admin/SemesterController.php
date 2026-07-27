<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SemesterController extends Controller
{
    /**
     * GET /api/v1/admin/semesters
     */
    public function index()
    {
        $semesters = Semester::with('academicYear')
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'data' => $semesters
        ]);
    }

    /**
     * POST /api/v1/admin/semesters
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year'     => 'nullable|string|max:50',
            'academic_year_db_id' => 'nullable|exists:academic_years,db_id',
            'semester_number'   => 'required|integer|in:1,2',
            'start_date'        => 'required|date',
            'length_weeks'      => 'required|integer|min:1',
            'is_active'         => 'boolean',
        ]);

        $semester = Semester::create([
            'public_id'           => (string) Str::uuid(),
            'academic_year'       => $validated['academic_year'] ?? null,
            'academic_year_db_id' => $validated['academic_year_db_id'] ?? null,
            'semester_number'     => $validated['semester_number'],
            'start_date'          => $validated['start_date'],
            'length_weeks'        => $validated['length_weeks'],
            'is_active'           => $validated['is_active'] ?? false,
        ]);

        return response()->json([
            'message' => 'Semester created successfully',
            'data'    => $semester
        ], 201);
    }

    /**
     * GET /api/v1/admin/semesters/active
     */
    public function active()
    {
        $semester = Semester::with('academicYear')
            ->where('is_active', true)
            ->first();

        if (!$semester) {
            return response()->json([
                'message' => 'No active semester found'
            ], 404);
        }

        return response()->json([
            'data' => $semester
        ]);
    }

    /**
     * POST /api/v1/admin/semesters/{public_id}/end
     */
    public function end($publicId)
    {
        $semester = Semester::where('public_id', $publicId)->firstOrFail();

        $semester->update(['is_active' => false]);

        return response()->json([
            'message' => 'Semester ended successfully',
            'data'    => $semester
        ]);
    }
}
