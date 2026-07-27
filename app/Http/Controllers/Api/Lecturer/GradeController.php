<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStudentMark;
use App\Models\Course;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class GradeController extends Controller
{
    /**
     * POST /api/v1/lecturer/courses/{course_public_id}/grades
     */
    public function submitBatch(Request $request, string $course_public_id)
    {
        $lecturer = Auth::user()->profile;

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        $course = Course::where('public_id', $course_public_id)->firstOrFail();

        $validated = $request->validate([
            'submissions' => 'required|array|min:1',
            'submissions.*.student_public_id' => 'required|exists:students,public_id',
            'submissions.*.total_score' => 'required|numeric|min:0|max:100',
        ]);

        $activeSemester = Semester::getActive();

        if (!$activeSemester) {
            return response()->json([
                'message' => 'No active semester currently found in the system.',
            ], 422);
        }

        // Confirm this lecturer is actually assigned to this course this semester,
        // rather than trusting the route alone.
        $isAssigned = $lecturer->timetableEntries()
            ->where('course_db_id', $course->db_id)
            ->where('semester_db_id', $activeSemester->db_id)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'message' => 'You are not assigned to this course for the active semester.',
            ], 403);
        }

        $publicIds = array_column($validated['submissions'], 'student_public_id');
        $studentDbIdMap = Student::whereIn('public_id', $publicIds)->pluck('db_id', 'public_id');

        $jobs = [];
        foreach ($validated['submissions'] as $submission) {
            $studentDbId = $studentDbIdMap[$submission['student_public_id']] ?? null;

            if ($studentDbId) {
                $jobs[] = new ProcessStudentMark(
                    $studentDbId,
                    $course->db_id,
                    $activeSemester->db_id,
                    $submission['total_score']
                );
            }
        }

        if (empty($jobs)) {
            return response()->json(['message' => 'No valid student records found.'], 422);
        }

        try {
            $batch = Bus::batch($jobs)
                ->name('Grading Batch: ' . $course->public_id)
                ->allowFailures()
                ->dispatch();

            return response()->json([
                'message' => 'Grades are being processed.',
                'batch_id' => $batch->id,
            ], 202);
        } catch (\Throwable $e) {
            Log::error('Grade batch dispatch failed', [
                'message' => $e->getMessage(),
                'course_public_id' => $course_public_id,
                'jobs_count' => count($jobs),
            ]);

            return response()->json([
                'message' => 'Failed to dispatch grading batch.',
            ], 500);
        }
    }
}
