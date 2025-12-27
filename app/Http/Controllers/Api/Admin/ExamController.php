<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSeason;
use App\Models\ExamSchedule;
use App\Models\Semester;
use App\Models\Course;
use App\Models\ExamGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Jobs\GenerateExamNumbers; // We will assume this job exists

class ExamController extends Controller
{
    // --- SEASONS ---
    public function storeSeason(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'semester_public_id' => 'required|exists:semesters,public_id',
        ]);

        $semId = Semester::getIdFromPublicId($validated['semester_public_id']);

        $season = ExamSeason::create([
            'name' => $validated['name'],
            'semester_id' => $semId,
            'is_active' => true
        ]);

        return response()->json($season, 201);
    }

    public function generateNumbers(Request $request, $publicId)
    {
        $season = ExamSeason::where('public_id', $publicId)->firstOrFail();

        // Dispatch Job to handle heavy processing in background
        // Make sure to run: php artisan make:job GenerateExamNumbers
        dispatch(new \App\Jobs\GenerateExamNumbers($season));

        return response()->json(['message' => 'Generation started in background'], 202);
    }

    // --- SCHEDULES ---
    public function storeSchedule(Request $request)
    {
        $validated = $request->validate([
            'exam_season_public_id' => 'required|exists:exam_seasons,public_id',
            'course_public_id' => 'required|exists:courses,public_id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer',
            'location' => 'required|string',
            'exam_number_start' => 'required|string',
            'exam_number_end' => 'required|string',
        ]);

        $seasonId = ExamSeason::getIdFromPublicId($validated['exam_season_public_id']);
        $courseId = Course::getIdFromPublicId($validated['course_public_id']);

        $schedule = ExamSchedule::create([
            'exam_season_id' => $seasonId,
            'course_id' => $courseId,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'duration_minutes' => $validated['duration_minutes'],
            'location' => $validated['location'],
        ]);

        // Create the Exam Group (Physical Batch)
        ExamGroup::create([
            'exam_schedule_id' => $schedule->id,
            'exam_number_start' => $validated['exam_number_start'],
            'exam_number_end' => $validated['exam_number_end'],
        ]);

        return response()->json($schedule, 201);
    }
}
