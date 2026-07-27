<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamPaper;
use App\Models\ExamSeason;
use App\Models\Program;
use Illuminate\Http\Request;

class ExamScheduleController extends Controller
{
    /**
     * GET /api/v1/admin/exams/schedules
     */
    public function index(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id',
            'season_public_id' => 'required|exists:exam_seasons,public_id',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $season = ExamSeason::where('public_id', $request->season_public_id)->firstOrFail();

        $papers = ExamPaper::with('course')
            ->where('exam_season_db_id', $season->db_id)
            ->where('program_db_id', $program->db_id)
            ->get();

        return response()->json([
            'data' => $papers->map(fn (ExamPaper $paper) => $this->format($paper)),
        ]);
    }

    /**
     * POST /api/v1/admin/exams/schedules
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'season_public_id' => 'required|exists:exam_seasons,public_id',
            'course_public_id' => 'required|exists:courses,public_id',
            'program_public_id' => 'required|exists:programs,public_id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:30',
            'location' => 'required|string',
        ]);

        $season = ExamSeason::where('public_id', $validated['season_public_id'])->firstOrFail();
        $course = Course::where('public_id', $validated['course_public_id'])->firstOrFail();
        $program = Program::where('public_id', $validated['program_public_id'])->firstOrFail();

        // A course only makes sense on this paper if it belongs to the same
        // department as the program (no curriculum table to check against yet).
        if ($course->department_db_id !== $program->department_db_id) {
            return response()->json([
                'message' => 'This course does not belong to the selected program\'s department.',
            ], 422);
        }

        $paper = ExamPaper::updateOrCreate(
            [
                'exam_season_db_id' => $season->db_id,
                'program_db_id' => $program->db_id,
                'course_db_id' => $course->db_id,
            ],
            [
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
                'duration_minutes' => $validated['duration_minutes'],
                'location' => $validated['location'],
            ]
        );

        return response()->json([
            'message' => 'Exam scheduled successfully.',
            'data' => $this->format($paper->load('course')),
        ], 201);
    }

    private function format(ExamPaper $paper): array
    {
        return [
            'public_id' => $paper->public_id,
            'date' => $paper->date?->format('Y-m-d'),
            'start_time' => $paper->start_time,
            'duration_minutes' => $paper->duration_minutes,
            'location' => $paper->location,
            'course' => $paper->course ? [
                'public_id' => $paper->course->public_id,
                'name' => $paper->course->name,
            ] : null,
        ];
    }
}
