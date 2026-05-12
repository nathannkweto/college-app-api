<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessStudentMark;
use App\Models\Student;
use App\Models\Semester; // Imported the Semester model

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

        // 2. Resolve the Semester String to an Internal Integer ID
        // Expected incoming string format: "2024-2025 Semester 1"
        $semesterParts = explode(' Semester ', $validated['semester']);
        
        if (count($semesterParts) !== 2) {
            return response()->json([
                'message' => 'Invalid semester format. Expected format: "YYYY-YYYY Semester N"'
            ], 422);
        }

        $academicYear = trim($semesterParts[0]); // "2024-2025"
        $semesterNumber = trim($semesterParts[1]); // "1"

        // Lookup the exact Semester record in the database
        $semesterRecord = Semester::where('academic_year', $academicYear)
            ->where('semester_number', $semesterNumber)
            ->first();

        if (!$semesterRecord) {
            return response()->json([
                'message' => 'Semester not found in database for: ' . $validated['semester']
            ], 422);
        }

        $internalSemesterId = $semesterRecord->id;

        // 3. Resolve Student UUIDs to Internal IDs
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
                    $internalStudentId,              
                    $validated['program_course_id'], 
                    $internalSemesterId,             // ✅ Passing the INTEGER ID here!
                    $submission['total_score']       
                );
            }
        }

        if (empty($jobs)) {
            return response()->json(['message' => 'No valid student records found.'], 422);
        }

        // 4. Dispatch Batch
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