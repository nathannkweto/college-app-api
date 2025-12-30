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
     * Schedule a specific exam paper.
     */
    public function store(Request $request)
    {
        $request->validate([
            'season_public_id' => 'required|exists:exam_seasons,public_id',
            'course_public_id' => 'required|exists:courses,public_id',
            'program_public_id' => 'required|exists:programs,public_id', // Needed for the DB column
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:30',
            'location' => 'required|string',
        ]);

        $season = ExamSeason::where('public_id', $request->season_public_id)->firstOrFail();
        $course = Course::where('public_id', $request->course_public_id)->firstOrFail();
        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();


        $paper = ExamPaper::create([
            'exam_season_id' => $season->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'duration_minutes' => $request->duration_minutes,
            'location' => $request->location,
        ]);

        return response()->json(['message' => 'Exam scheduled successfully.', 'data' => $paper], 201);
    }
}
