<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamPaper;
use App\Models\ExamSeason;
use App\Models\Program;
use App\Models\ProgramCourse;
use Illuminate\Http\Request;

class ExamScheduleController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id',
            'season_public_id'  => 'required|exists:exam_seasons,public_id',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $season = ExamSeason::where('public_id', $request->season_public_id)->firstOrFail();

        // Query papers via the pivot relationship
        $papers = ExamPaper::query()
            ->with(['programCourse.course']) // Eager load the nested course info
            ->where('exam_season_id', $season->id)
            ->whereHas('programCourse', function ($q) use ($program) {
                $q->where('program_id', $program->id);
            })
            ->get()
            ->map(function ($paper) {
                // OPTIONAL: Flatten the response for the frontend
                // so it looks like the old format: paper.course.name
                $paper->setRelation('course', $paper->programCourse->course);
                return $paper;
            });

        return response()->json(['data' => $papers]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'season_public_id'  => 'required|exists:exam_seasons,public_id',
            'course_public_id'  => 'required|exists:courses,public_id',
            'program_public_id' => 'required|exists:programs,public_id',
            'date'              => 'required|date',
            'start_time'        => 'required|date_format:H:i',
            'duration_minutes'  => 'required|integer|min:30',
            'location'          => 'required|string',
        ]);

        $season = ExamSeason::where('public_id', $request->season_public_id)->firstOrFail();
        $course = Course::where('public_id', $request->course_public_id)->firstOrFail();
        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();

        // 1. Find the Pivot ID
        $pivot = ProgramCourse::where('program_id', $program->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$pivot) {
            return response()->json([
                'message' => 'This course is not part of the selected program.'
            ], 422);
        }

        // 2. Upsert using the Pivot ID
        $paper = ExamPaper::updateOrCreate(
            [
                'exam_season_id'    => $season->id,
                'program_course_id' => $pivot->id,
            ],
            [
                'date'             => $request->date,
                'start_time'       => $request->start_time,
                'duration_minutes' => $request->duration_minutes,
                'location'         => $request->location,
            ]
        );

        // Manually attach the course object for the immediate JSON response
        $paper->setRelation('course', $course);

        return response()->json([
            'message' => 'Exam scheduled successfully.',
            'data' => $paper
        ], 201);
    }
}
