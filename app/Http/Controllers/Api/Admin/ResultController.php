<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment; // <--- CHANGED from ExamResult
use App\Models\Program;
use App\Models\ResultPublication;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultController extends Controller
{
    /**
     * Get list of students in a program with their result status.
     */
    public function programSummary(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required',
            'semester_public_id' => 'required'
        ]);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        // 1. Get Publication Status
        // Based on your schema, publication is per Semester (global) OR you might want to add program_id to the schema later.
        // For now, valid code based on your provided schema:
        $publication = ResultPublication::where('semester_id', $semester->id)->first();

        // 2. Fetch Students
        $students = Student::where('program_id', $program->id)
            ->where('status', 'active')
            ->get()
            ->map(function ($student) use ($semester) {

                // 3. Fetch Enrollments (replacing ExamResult)
                // We match the semester string (e.g. "2024-2025 Semester 1") if that's how it's stored,
                // or use a scope if you added semester_id to enrollments.
                $enrollments = Enrollment::where('student_id', $student->id)
                    ->where('semester', $semester->name) // Assuming 'semester' column is the string name
                    ->get();

                $resultsCount = $enrollments->count();

                // Calculate Failure (Grade 'F' or score < 50)
                $failedCount = $enrollments->filter(function($e) {
                    return $e->grade === 'F' || $e->score < 50;
                })->count();

                $avgScore = $resultsCount > 0 ? $enrollments->avg('score') : 0;

                // Simple Status Logic
                $status = ($resultsCount >= 1) ? 'Complete' : 'Pending';

                // Decision Logic
                $decision = 'PROMOTED';
                if ($failedCount > 0) $decision = 'REPEAT';
                if ($resultsCount == 0) $decision = 'NO_RESULTS';

                return [
                    'student_public_id' => $student->public_id,
                    'student_id'        => $student->student_id,
                    'first_name'        => $student->first_name,
                    'last_name'         => $student->last_name,
                    'courses_failed'    => $failedCount,
                    'average_score'     => round($avgScore, 2),
                    'semester_decision' => $decision,
                    'status'            => $status,
                ];
            });

        return response()->json([
            'is_published' => $publication ? (bool)$publication->is_published : false,
            'data'         => $students
        ]);
    }

    /**
     * Get a specific student's transcript.
     */
    public function studentTranscript(Request $request)
    {
        $request->validate([
            'student_public_id'  => 'required',
            'semester_public_id' => 'required'
        ]);

        // 1. Resolve IDs from Public IDs
        $student = Student::with('program')->where('public_id', $request->student_public_id)->firstOrFail();
        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        // 2. Fetch Enrollments
        // We query by semester_id (Foreign Key) as per your schema
        $results = Enrollment::with('programCourse.course')
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->get();

        // 3. Map Results
        // Only return what is strictly in the enrollment table + Course Info for context
        $mappedResults = $results->map(function ($res) {
            // Safe navigation to get course details via the pivot
            $course = $res->programCourse->course ?? null;

            return [
                // Context (Joined Data)
                'course_name' => $course ? $course->name : 'Unknown Course',
                'course_code' => $course ? $course->code : '---',

                // Enrollment Table Data
                'score'       => $res->score, // decimal(5,2)
                'grade'       => $res->grade, // string
            ];
        });

        return response()->json([
            'student' => $student,
            // We return the raw mapped list.
            // No GPA or standing calculations as they don't exist in the table.
            'results' => $mappedResults
        ]);
    }

    /**
     * Publish results for a Program + Semester.
     */
    public function publish(Request $request)
    {
        // 1. Validate both IDs are present
        $request->validate([
            'semester_public_id' => 'required|exists:semesters,public_id',
            'program_public_id'  => 'required|exists:programs,public_id',
        ]);

        // 2. Resolve Models
        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();
        $program  = Program::where('public_id', $request->program_public_id)->firstOrFail();

        // 3. Update or Create the Publication Record
        // We now scope this by BOTH Semester AND Program.
        ResultPublication::updateOrCreate(
            [
                'semester_id' => $semester->id,
                'program_id'  => $program->id, // <--- New logic
            ],
            [
                'is_published' => true,
                'updated_at'   => now()
            ]
        );

        return response()->json([
            'message' => "Results for {$program->name} ({$semester->name}) have been published."
        ]);
    }
}
