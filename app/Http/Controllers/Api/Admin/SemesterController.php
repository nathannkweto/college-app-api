<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index()
    {
        $semesters = Semester::with('academicYear')->orderByDesc('created_at')->get();

        // Transform the collection to match what Flutter expects
        return $semesters->map(function ($semester) {
            return [
                'id'              => $semester->id,
                'semester_number' => $semester->semester_number,
                'is_active'       => (bool) $semester->is_active,
                // Flatten the object into a simple string
                'academic_year'   => $semester->academicYear ? $semester->academicYear->year : 'Unknown',
                'created_at'      => $semester->created_at,
            ];
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string',
            'semester_number' => 'required|in:1,2',
        ]);

        $year = AcademicYear::firstOrCreate(['year' => $validated['academic_year']]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'semester_number' => $validated['semester_number'],
            'is_active' => true
        ]);

        // Return the same flattened structure for consistency
        return response()->json([
            'id'              => $semester->id,
            'semester_number' => $semester->semester_number,
            'is_active'       => (bool) $semester->is_active,
            'academic_year'   => $year->year,
        ], 201);
    }
}
