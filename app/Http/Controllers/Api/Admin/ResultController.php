<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Student;
use App\Models\Program;
use App\Models\Semester;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    /**
     * GET /api/v1/admin/results/program-summary
     */
    public function programSummary(Request $request)
    {
        $request->validate([
            'program_public_id'  => 'required|exists:programs,public_id',
            'semester_public_id' => 'nullable|exists:semesters,public_id',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();

        $query = Result::with(['student', 'course', 'semester'])
            ->whereHas('student', function ($q) use ($program) {
                $q->where('program_db_id', $program->db_id);
            });

        if ($request->filled('semester_public_id')) {
            $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();
            $query->where('semester_db_id', $semester->db_id);
        }

        $results = $query->get();
        $averagePassMark = $results->avg('pass_mark') ?? 50;

        $summary = [
            // Model::only() doesn't exist — Eloquent models aren't Collections.
            'program' => [
                'public_id' => $program->public_id,
                'name' => $program->name,
                'tag' => $program->tag,
            ],
            'total_results' => $results->count(),
            'average_score' => round($results->avg('score'), 2),
            'pass_count' => $results->where('score', '>=', $averagePassMark)->count(),
            'fail_count' => $results->where('score', '<', $averagePassMark)->count(),
            'results' => $results,
        ];

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/v1/admin/results/student-transcript
     */
    public function studentTranscript(Request $request)
    {
        $request->validate([
            'student_public_id' => 'required|exists:students,public_id',
        ]);

        $student = Student::with(['program', 'level'])
            ->where('public_id', $request->student_public_id)
            ->firstOrFail();

        $results = Result::with(['course', 'semester'])
            ->where('student_db_id', $student->db_id)
            ->orderBy('semester_db_id')
            ->get()
            ->groupBy('semester_db_id');

        return response()->json([
            'data' => [
                'student' => $student,
                'results' => $results,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/results/publish
     * Placeholder for now.
     */
    public function publish(Request $request)
    {
        return response()->json([
            'message' => 'Publish results endpoint - implementation pending',
        ], 501);
    }
}
