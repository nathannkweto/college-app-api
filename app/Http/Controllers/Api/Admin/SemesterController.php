<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Jobs\EnrollStudentForSemester;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SemesterController extends Controller
{
    /**
     * Get the currently active semester details.
     */
    public function active()
    {
        $semester = Semester::where('is_active', true)->first();

        if (!$semester) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'public_id'       => $semester->public_id,
                'academic_year'   => $semester->academic_year,
                'semester_number' => $semester->semester_number,
                'is_active'       => (bool) $semester->is_active,
                'start_date'      => $semester->start_date->format('Y-m-d'),
                'length_weeks'    => (int) $semester->length_weeks,
            ]
        ]);
    }

    public function index()
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get()->map(function($s) {
            return [
                'public_id'       => $s->public_id,
                'academic_year'   => $s->academic_year,
                'semester_number' => (int) $s->semester_number,
                'start_date'      => $s->start_date->format('Y-m-d'),
                'length_weeks'    => (int) $s->length_weeks,
                'is_active'       => (bool) $s->is_active,
            ];
        });

        return response()->json(['data' => $semesters]);
    }

    /**
     * Create a new semester.
     * Automatically deactivates previous semesters if 'is_active' is true.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string',
            'semester_number' => 'required|integer|in:1,2,3',
            'start_date' => 'required|date',
            'length_weeks' => 'required|integer|min:1',
            'is_active' => 'boolean'
        ]);

        // 1. Create Semester
        $semester = \DB::transaction(function () use ($request, $validated) {
            if ($request->is_active) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }
            return Semester::create($validated);
        });

        // 2. Trigger Bulk Enrollment (Only if active)
        if ($semester->is_active) {
            $this->triggerBulkEnrollment($semester);
        }

        return response()->json([
            'message' => 'Semester created and enrollment processing started.',
            'data' => $semester
        ], 201);
    }

    protected function triggerBulkEnrollment(Semester $semester)
    {
        Log::info("Starting Bulk Enrollment for Semester: " . $semester->id);
        // Define if this is the "Start of Academic Year" logic
        // Assuming semester_number 1 is the start of the year.
        $isStartOfYear = ($semester->semester_number == 1);

        $count = Student::where('status', 'active')->count();
        Log::info("Found {$count} active students.");

        if ($count === 0) {
            Log::warning("No active students found. Aborting batch.");
            return;
        }

        // Fetch all active students
        // We use cursor() or chunk() to avoid loading all into memory
        $students = Student::where('status', 'active')->select('id')->cursor();

        $jobs = [];
        foreach ($students as $student) {
            $jobs[] = new EnrollStudentForSemester(
                $student->id,
                $semester->id,
                $isStartOfYear
            );
        }

        Log::info("Dispatching batch with " . count($jobs) . " jobs.");
        // Chunking the jobs into batch (Bus::batch accepts array, careful with memory)
        // If > 2000 students, use chunks. Here is a safer way:
        $batch = Bus::batch($jobs)
            ->name('Semester Enrollment: ' . $semester->academic_year)
            ->allowFailures()
            ->dispatch();

        Log::info("Batch Dispatched. ID: " . $batch->id);
    }

    /**
     * End a semester (Mark as inactive).
     */
    public function end($public_id)
    {
        $semester = Semester::where('public_id', $public_id)->firstOrFail();

        $semester->update(['is_active' => false]);

        return response()->json(['message' => 'Semester ended successfully.']);
    }
}
