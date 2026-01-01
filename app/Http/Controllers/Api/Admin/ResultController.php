<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
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
            'semester_public_id' => 'required' // Make sure this is passed
        ]);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        // 1. Get Publication Status
        $publication = ResultPublication::where('program_id', $program->id)
            ->where('semester_id', $semester->id)
            ->first();

        // 2. Get expected course count for this program/semester
        // Note: You need a logic to know how many courses a student *should* have taken.
        // For simplicity, we assume the program has a defined curriculum for this semester.
        // This is a simplified count.
        $expectedCourseCount = $program->courses()
            ->wherePivot('semester_sequence', 1) // Logic needs to adjust based on student year, but keeping simple for now
            ->count();
        if ($expectedCourseCount == 0) $expectedCourseCount = 5; // Fallback to prevent divide by zero

        // 3. Fetch Students with their aggregated results
        $students = Student::where('program_id', $program->id)
            ->where('status', 'active') // Only active students
            ->get()
            ->map(function ($student) use ($semester, $expectedCourseCount) {

                // Fetch results for this student in this semester
                $results = ExamResult::where('student_id', $student->id)
                    ->where('semester_id', $semester->id)
                    ->get();

                $resultsCount = $results->count();
                $failedCount = $results->where('is_passed', false)->count();
                $avgScore = $resultsCount > 0 ? $results->avg('score') : 0;

                // Status: Complete if they have results for at least 80% of expected courses
                // (You can adjust this logic)
                $status = ($resultsCount >= 1) ? 'Complete' : 'Pending';

                // Decision
                $decision = 'PROMOTED';
                if ($failedCount > 0) $decision = 'REPEAT'; // Simple logic
                if ($resultsCount == 0) $decision = 'NO_RESULTS';

                return [
                    'student_public_id' => $student->public_id,
                    'student_id' => $student->student_id, // The readable ID (e.g., STU-2024-001)
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'courses_failed' => $failedCount,
                    'average_score' => round($avgScore, 2),
                    'semester_decision' => $decision,
                    'status' => $status, // 'Complete' or 'Pending'
                ];
            });

        return response()->json([
            'is_published' => $publication ? $publication->is_published : false,
            'data' => $students
        ]);
    }

    /**
     * Get a specific student's transcript.
     */
    public function studentTranscript(Request $request)
    {
        $request->validate([
            'student_public_id' => 'required',
            'semester_public_id' => 'required'
        ]);

        $student = Student::with('program')->where('public_id', $request->student_public_id)->firstOrFail();
        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        $results = ExamResult::with(['course'])
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->get();

        $gpa = $results->avg('score'); // Simplified GPA (Average Score)

        $mappedResults = $results->map(function ($res) {
            return [
                'course_name' => $res->course->name,
                'course_code' => $res->course->code,
                'total_score' => $res->score,
                'grade' => $res->grade,
                'status' => $res->is_passed ? 'PASS' : 'FAIL',
            ];
        });

        // Determine Academic Standing
        $failed = $results->where('is_passed', false)->count();
        $standing = $failed == 0 ? 'Good Standing' : 'Academic Warning';

        return response()->json([
            'student' => $student, // Returns full student object as per ref schema
            'semester_gpa' => round($gpa, 2),
            'academic_standing' => $standing,
            'results' => $mappedResults
        ]);
    }
    /**
     * Publish results for a Program + Semester.
     */
    public function publish(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id',
            'semester_public_id' => 'required|exists:semesters,public_id',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        DB::transaction(function () use ($program, $semester) {
            // 1. Update or Create the Publication Record
            ResultPublication::updateOrCreate(
                [
                    'program_id' => $program->id,
                    'semester_id' => $semester->id
                ],
                [
                    'is_published' => true,
                    'published_at' => now()
                ]
            );

            // 2. Bulk Update: Find all students in this program
            // Then find their results for this semester and flip 'is_published'
            $studentIds = Student::where('program_id', $program->id)->pluck('id');

            ExamResult::whereIn('student_id', $studentIds)
                ->where('semester_id', $semester->id)
                ->update(['is_published' => true]);
        });

        return response()->json([
            'message' => "Results for {$program->name} have been published. Students can now view them."
        ]);
    }
}
