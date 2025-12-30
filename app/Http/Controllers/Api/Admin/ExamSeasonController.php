<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSeason;
use App\Models\Semester;
use Illuminate\Http\Request;

class ExamSeasonController extends Controller
{
    /**
     * Create a new Exam Season (e.g., "Finals 2025").
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'semester_public_id' => 'required|exists:semesters,public_id',
        ]);

        $semester = Semester::where('public_id', $validated['semester_public_id'])->firstOrFail();

        $season = ExamSeason::create([
            'name' => $validated['name'],
            'semester_id' => $semester->id,
        ]);

        return response()->json([
            'message' => 'Exam Season created successfully.',
            'data' => [
                'public_id' => $season->public_id,
                'name' => $season->name,
                'semester' => $semester->academic_year
            ]
        ], 201);
    }
}
