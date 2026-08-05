<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    /**
     * List semesters.
     */
    public function index(Request $request)
    {
        $semesters = Semester::with('academicYear')->orderByDesc('start_date')->paginate(15);

        return response()->json([
            'data' => $semesters->getCollection()->map(fn (Semester $s) => $this->format($s)),
            'meta' => [
                'current_page' => $semesters->currentPage(),
                'last_page' => $semesters->lastPage(),
                'total' => $semesters->total(),
            ],
        ]);
    }

    /**
     * Create a semester.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year_public_id' => 'required|string|exists:academic_years,public_id',
            'semester_number' => 'required|integer|min:1|max:255',
            'start_date' => 'required|date',
            'length_weeks' => 'required|integer|min:1',
        ]);

        $academicYear = AcademicYear::where('public_id', $validated['academic_year_public_id'])->firstOrFail();

        $semester = Semester::create([
            'academic_year_db_id' => $academicYear->db_id,
            'academic_year' => $academicYear->name,
            'semester_number' => $validated['semester_number'],
            'start_date' => $validated['start_date'],
            'length_weeks' => $validated['length_weeks'],
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Semester created successfully.',
            'data' => $this->format($semester->load('academicYear')),
        ], 201);
    }

    /**
     * Get the currently active semester.
     */
    public function active(Request $request)
    {
        $semester = Semester::active()->with('academicYear')->first();

        if (!$semester) {
            return response()->json(['message' => 'No active semester found.'], 404);
        }

        return response()->json(['data' => $this->format($semester)]);
    }

    /**
     * End (deactivate) a semester.
     */
    public function end(Request $request, $public_id)
    {
        $semester = Semester::where('public_id', $public_id)->firstOrFail();
        $semester->update(['is_active' => false]);

        return response()->json([
            'message' => 'Semester ended successfully.',
            'data' => $this->format($semester),
        ]);
    }

    private function format(Semester $semester): array
    {
        return [
            'public_id' => $semester->public_id,
            // Spec wants a flat string here, matching the legacy `academic_year`
            // column directly rather than the `academic_year_db_id` relation.
            'academic_year' => $semester->academic_year,
            'semester_number' => $semester->semester_number,
            'is_active' => $semester->is_active,
            'start_date' => $semester->start_date?->format('Y-m-d'),
            'length_weeks' => $semester->length_weeks,
        ];
    }
}
