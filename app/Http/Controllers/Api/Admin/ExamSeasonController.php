<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSeason;
use App\Models\Semester;
use Illuminate\Http\Request;

class ExamSeasonController extends Controller
{
    /**
     * GET /api/v1/admin/exams/seasons
     */
    public function index()
    {
        $seasons = ExamSeason::with('semester')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $seasons->map(fn (ExamSeason $season) => $this->format($season)),
        ]);
    }

    /**
     * GET /api/v1/admin/exams/seasons/active
     */
    public function active()
    {
        $season = ExamSeason::active()?->load('semester');

        if (!$season) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->format($season)]);
    }

    /**
     * POST /api/v1/admin/exams/seasons
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'semester_public_id' => 'required|exists:semesters,public_id',
        ]);

        $semester = Semester::where('public_id', $validated['semester_public_id'])->firstOrFail();

        // Only one active exam season at a time.
        ExamSeason::where('is_active', true)->update(['is_active' => false]);

        $season = ExamSeason::create([
            'name' => $validated['name'],
            'semester_db_id' => $semester->db_id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Exam season created successfully.',
            'data' => $this->format($season->load('semester')),
        ], 201);
    }

    /**
     * POST /api/v1/admin/exams/seasons/{public_id}/end
     */
    public function endSeason($public_id)
    {
        $season = ExamSeason::where('public_id', $public_id)->firstOrFail();
        $season->update(['is_active' => false]);

        return response()->json([
            'message' => 'Exam season ended successfully.',
            'data' => $this->format($season->load('semester')),
        ]);
    }

    private function format(ExamSeason $season): array
    {
        return [
            'public_id' => $season->public_id,
            'name' => $season->name,
            'is_active' => $season->is_active,
            'created_at' => $season->created_at?->toIso8601String(),
            'semester' => $season->semester ? [
                'public_id' => $season->semester->public_id,
                'academic_year' => $season->semester->academic_year,
                'semester_number' => $season->semester->semester_number,
                'is_active' => $season->semester->is_active,
                'start_date' => $season->semester->start_date?->format('Y-m-d'),
                'length_weeks' => $season->semester->length_weeks,
            ] : null,
        ];
    }
}
