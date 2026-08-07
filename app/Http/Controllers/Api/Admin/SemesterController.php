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
     * GET /semesters
     */
    public function index(Request $request)
    {
        $semesters = Semester::with('academicYear')->orderByDesc('start_date')->get();

        return response()->json([
            'data' => $semesters->map(fn (Semester $s) => $this->format($s)),
        ]);
    }

    /**
     * Create a semester.
     * POST /semesters
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string',
            'semester_number' => 'required|integer|in:1,2',
            'start_date' => 'required|date',
            'length_weeks' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
        ]);

        // Lookup AcademicYear model if database requires relationship binding
        $academicYear = AcademicYear::where('name', $validated['academic_year'])
            ->orWhere('public_id', $validated['academic_year'])
            ->first();

        $semester = Semester::create([
            'academic_year_db_id' => $academicYear?->db_id,
            'academic_year' => $validated['academic_year'],
            'semester_number' => $validated['semester_number'],
            'start_date' => $validated['start_date'],
            'length_weeks' => $validated['length_weeks'],
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'data' => $this->format($semester),
        ], 201);
    }

    /**
     * Get the currently active semester.
     * GET /semesters/active
     */
    public function active(Request $request)
    {
        $semester = Semester::active()->with('academicYear')->first();

        if (!$semester) {
            return response()->json(['message' => 'No active semester found.'], 404);
        }

        return response()->json([
            'data' => $this->format($semester),
        ]);
    }

    /**
     * End (deactivate) a semester.
     * POST /semesters/{public_id}/end
     */
    public function end(Request $request, $public_id)
    {
        $semester = Semester::where('public_id', $public_id)->firstOrFail();
        $semester->update(['is_active' => false]);

        return response()->json([
            'data' => $this->format($semester),
        ], 200);
    }

    /**
     * Formats the model strictly matching the OpenAPI `Semester` schema.
     */
    private function format(Semester $semester): array
    {
        return [
            'public_id' => (string) $semester->public_id,
            'academic_year' => (string) $semester->academic_year,
            'semester_number' => (int) $semester->semester_number,
            'is_active' => (bool) $semester->is_active,
            'start_date' => $semester->start_date ? \Carbon\Carbon::parse($semester->start_date)->format('Y-m-d') : null,
            'length_weeks' => (int) $semester->length_weeks,
        ];
    }
}