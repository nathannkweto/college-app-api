<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessStudentMark;
use App\Models\Student;
use App\Models\Semester;

class GradeController extends Controller
{
    public function submitBatch(Request $request)
    {
        // 1. Validate the incoming payload
        $validated = $request->validate([
            'program_course_id' => 'required|integer|exists:program_courses,id',
            // We no longer require the frontend to dictate the semester!
            // You can leave this here as 'nullable' so the Flutter app doesn't break when it sends it.
            'semester'          => 'nullable|string', 
            'submissions'       => 'required|array',
            'submissions.*.student_public_id' => 'required|exists:students,public_id',
            'submissions.*.total_score'       => 'required|numeric|min:0|max:100',
        ]);

        // 2. Get the Active Semester directly from the database
        // This uses the active() helper defined in your Semester model
        $semesterRecord = Semester::active();
        
        if (!$semesterRecord) {
            return response()->json([
                'message' => 'No active semester currently found in the system.'
            ], 422);
        }

        $internalSemesterId = $semesterRecord->id; // ✅ We have our integer!

        // 3. Resolve Student UUIDs to Internal IDs
        $publicIds = array_column($validated['submissions'], 'student_public_id');

        $studentMap = Student::whereIn('public_id', $publicIds)
            ->pluck('id', 'public_id');

        $jobs = [];
        foreach ($validated['submissions'] as $submission) {
            $publicId = $submission['student_public_id'];

            if (isset($studentMap[$publicId])) {
                $internalStudentId = $studentMap[$publicId];

                $jobs[] = new ProcessStudentMark(
                    $internalStudentId,              
                    $validated['program_course_id'], 
                    $internalSemesterId,             // ✅ Passing the active integer ID
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