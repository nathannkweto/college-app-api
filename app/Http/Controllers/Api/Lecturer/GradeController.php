<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessStudentMark;
use App\Models\Student; // Ensure this is imported

class GradeController extends Controller
{
    public function submitBatch(Request $request)
    {
        // 1. Validate using the NEW field names (Public API Contract)
        $validated = $request->validate([
            'program_course_id' => 'required|integer|exists:program_courses,id',
            'semester'          => 'required|string',
            'submissions'       => 'required|array',

            // Validate UUID exists in the public_id column
            'submissions.*.student_public_id' => 'required|exists:students,public_id',
            // Validate score (mapped from 'total_score')
            'submissions.*.total_score'       => 'required|numeric|min:0|max:100',
        ]);

        // 2. Resolve Student UUIDs to Internal IDs
        // We need to fetch the internal integer IDs to pass to ProcessStudentMark
        $publicIds = array_column($validated['submissions'], 'student_public_id');

        // Map: 'uuid-string' => 101 (integer)
        $studentMap = Student::whereIn('public_id', $publicIds)
            ->pluck('id', 'public_id');

        $jobs = [];
        foreach ($validated['submissions'] as $submission) {
            $publicId = $submission['student_public_id'];

            // Only process if we found the internal ID
            if (isset($studentMap[$publicId])) {
                $internalStudentId = $studentMap[$publicId];

                $jobs[] = new ProcessStudentMark(
                    $internalStudentId,              // Internal ID (Converted)
                    $validated['program_course_id'], // Internal ID (Passed directly)
                    $validated['semester'],
                    $submission['total_score']       // Score (Renamed from mark)
                );
            }
        }

        if (empty($jobs)) {
            return response()->json(['message' => 'No valid student records found.'], 422);
        }

        // 3. Dispatch Batch
        try {
            $batch = Bus::batch($jobs)
                ->name('Grading Batch: ' . $validated['program_course_id'])
                ->allowFailures()
                ->dispatch();

            return response()->json([
                'message' => 'Grades are being processed.',
                'batch_id' => $batch->id
            ], 202);
        } catch (\Throwable $e) {
            \Log::error('Grade batch dispatch failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'program_course_id' => $validated['program_course_id'],
                'jobs_count' => count($jobs),
            ]);

            return response()->json([
                'message' => 'Failed to dispatch grading batch.',
            ], 500);
        }
    }
}
