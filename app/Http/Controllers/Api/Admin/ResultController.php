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
     * Get pass/fail stats for a program in the active semester.
     */
    public function programSummary(Request $request)
    {
        $request->validate(['program_public_id' => 'required']);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $semester = Semester::active();

        if (!$semester) return response()->json(['data' => []]);

        // 1. Get Publication Status
        $publication = ResultPublication::where('program_id', $program->id)
            ->where('semester_id', $semester->id)
            ->first();

        // 2. Aggregate Results
        // Get all students in this program
        $totalStudents = Student::where('program_id', $program->id)->where('status', 'active')->count();

        // Count Passed/Failed results for this semester/program combo
        // TODO: This is a rough summary (Pass = passed all courses? or just sum of passed courses?)

        $courseStats = DB::table('exam_results')
            ->join('courses', 'exam_results.course_id', '=', 'courses.id')
            ->select(
                'courses.name as course_name',
                'courses.code as course_code',
                DB::raw('count(*) as total_attempts'),
                DB::raw('sum(case when is_passed = 1 then 1 else 0 end) as passed_count'),
                DB::raw('sum(case when is_passed = 0 then 1 else 0 end) as failed_count')
            )
            ->where('exam_results.semester_id', $semester->id)
            ->whereIn('exam_results.student_id', function($q) use ($program) {
                $q->select('id')->from('students')->where('program_id', $program->id);
            })
            ->groupBy('courses.id', 'courses.name', 'courses.code')
            ->get();

        return response()->json([
            'program' => $program->name,
            'semester' => $semester->academic_year,
            'is_published' => $publication ? $publication->is_published : false,
            'published_at' => $publication ? $publication->published_at : null,
            'student_count' => $totalStudents,
            'course_performance' => $courseStats
        ]);
    }

    /**
     * Get a specific student's full transcript.
     */
    public function studentTranscript(Request $request)
    {
        $request->validate(['student_public_id' => 'required']);

        $student = Student::with('program')->where('public_id', $request->student_public_id)->firstOrFail();

        $results = ExamResult::with(['course', 'semester'])
            ->where('student_id', $student->id)
            ->orderBy('semester_id') // Group by semester implicitly via order
            ->get()
            ->map(function ($res) {
                return [
                    'semester' => $res->semester->academic_year . ' (' . $res->semester->semester_number . ')',
                    'course' => $res->course->name,
                    'code' => $res->course->code,
                    'score' => $res->score,
                    'grade' => $res->grade,
                    'is_passed' => $res->is_passed,
                    'status' => $res->is_published ? 'Published' : 'Draft'
                ];
            });

        return response()->json([
            'student' => $student->first_name . ' ' . $student->last_name,
            'program' => $student->program->name,
            'transcript' => $results
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
