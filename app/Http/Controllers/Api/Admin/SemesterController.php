<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnrollStudentForSemester;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SemesterController extends Controller
{
    /**
     * List semesters.
     */
    public function index(Request $request)
    {
        $semesters = Semester::with('academicYear')->orderByDesc('start_date')->get();

        return response()->json([
            'data' => $semesters->map(fn (Semester $s) => $this->format($s)),
        ]);
    }

    /**
     * Create a semester and trigger bulk enrollment if active.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string',
            'semester_number' => 'required|integer|in:1,2,3',
            'start_date' => 'required|date',
            'length_weeks' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $isActive = $request->boolean('is_active', false);

        // 1. Transactionally create AcademicYear (if missing), update active statuses, and save Semester
        $semester = DB::transaction(function () use ($validated, $isActive) {
            // Find or create AcademicYear to ensure academic_year_db_id is populated
            $query = AcademicYear::where('name', $validated['academic_year']);
            if (Str::isUuid($validated['academic_year'])) {
                $query->orWhere('public_id', $validated['academic_year']);
            }
            $academicYear = $query->first();

            if (!$academicYear) {
                $academicYear = AcademicYear::create([
                    'name' => $validated['academic_year'],
                    'is_active' => false,
                ]);
            }

            // Deactivate existing active semesters if this new semester is active
            if ($isActive) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }

            return Semester::create([
                'academic_year_db_id' => $academicYear->db_id,
                'academic_year'       => $academicYear->name,
                'semester_number'     => $validated['semester_number'],
                'start_date'          => $validated['start_date'],
                'length_weeks'        => $validated['length_weeks'],
                'is_active'           => $isActive,
            ]);
        });

        // 2. Trigger Bulk Enrollment (Only if active)
        if ($semester->is_active) {
            $this->triggerBulkEnrollment($semester);
        }

        return response()->json([
            'message' => 'Semester created and enrollment processing started.',
            'data' => $this->format($semester),
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

        return response()->json([
            'data' => $this->format($semester),
        ]);
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

    /**
     * Dispatches bulk enrollment jobs for active students.
     */
    protected function triggerBulkEnrollment(Semester $semester)
    {
        $semesterDbId = $semester->db_id ?? $semester->id;
        Log::info("Starting Bulk Enrollment for Semester DB ID: {$semesterDbId}");

        $isStartOfYear = ($semester->semester_number == 1);

        $count = Student::where('status', 'active')->count();
        Log::info("Found {$count} active students.");

        if ($count === 0) {
            Log::warning("No active students found. Aborting batch.");
            return;
        }

        $students = Student::where('status', 'active')->select('db_id', 'id')->cursor();

        $jobs = [];
        foreach ($students as $student) {
            $studentDbId = $student->db_id ?? $student->id;
            $jobs[] = new EnrollStudentForSemester(
                $studentDbId,
                $semesterDbId,
                $isStartOfYear
            );
        }

        Log::info("Dispatching batch with " . count($jobs) . " jobs.");

        $batch = Bus::batch($jobs)
            ->name('Semester Enrollment: ' . $semester->academic_year)
            ->allowFailures()
            ->dispatch();

        Log::info("Batch Dispatched. ID: " . $batch->id);
    }

    /**
     * Formats the model matching the OpenAPI schema.
     */
    private function format(Semester $semester): array
    {
        return [
            'public_id'       => (string) $semester->public_id,
            'academic_year'   => (string) $semester->academic_year,
            'semester_number' => (int) $semester->semester_number,
            'is_active'       => (bool) $semester->is_active,
            'start_date'      => $semester->start_date ? \Carbon\Carbon::parse($semester->start_date)->format('Y-m-d') : null,
            'length_weeks'    => (int) $semester->length_weeks,
        ];
    }
}