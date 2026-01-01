<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSeason;
use App\Models\Semester;
use Illuminate\Http\Request;

class ExamSeasonController extends Controller
{
    /**
     * Get all exam seasons (History).
     */
    public function index()
    {
        // Eager load semester to show which academic year/sem it belongs to
        $seasons = ExamSeason::with('semester')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $seasons->map(function ($season) {
                return [
                    'public_id' => $season->public_id,
                    'name' => $season->name,
                    'is_active' => (bool) $season->is_active,
                    'semester' => [
                        'public_id' => $season->semester->public_id,
                        'name' => "Semester " . $season->semester->semester_number,
                        'academic_year' => $season->semester->academic_year,
                    ],
                    'created_at' => $season->created_at->toIso8601String(),
                ];
            })
        ]);
    }

    /**
     * Get the currently active exam season details.
     */
    public function active()
    {
        // Find the first active season
        $season = ExamSeason::where('is_active', true)
            ->with('semester')
            ->first();

        if (!$season) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'public_id' => $season->public_id,
                'name' => $season->name,
                'is_active' => true,
                'semester' => [
                    'public_id' => $season->semester->public_id,
                    'semester_number' => $season->semester->semester_number,
                    'academic_year' => $season->semester->academic_year,
                ]
            ]
        ]);
    }

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

        // LOGIC CHECK:
        // Usually, you only want ONE active exam season at a time.
        // We should deactivate any currently active seasons before starting a new one.
        ExamSeason::where('is_active', true)->update(['is_active' => false]);

        // Create the new season (Default migration sets is_active = true)
        // Assuming HasPublicId trait handles the UUID generation automatically.
        $season = ExamSeason::create([
            'name' => $validated['name'],
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Exam Season created successfully.',
            'data' => [
                'public_id' => $season->public_id,
                'name' => $season->name,
                'is_active' => true
            ]
        ], 201);
    }

    /**
     * End an exam season (Set is_active = false).
     */
    public function endSeason($publicId)
    {
        $season = ExamSeason::where('public_id', $publicId)->firstOrFail();

        $season->update(['is_active' => false]);

        return response()->json([
            'message' => 'Exam season ended successfully.',
            'data' => [
                'public_id' => $season->public_id,
                'is_active' => false
            ]
        ]);
    }
}
