<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamSeason;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    /**
     * GET /api/v1/student/exams/upcoming
     */
    public function upcoming(Request $request)
    {
        $student = Auth::user()->profile;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        if (!$student->program_db_id) {
            return response()->json(['data' => []]);
        }

        $activeSemester = Semester::getActive();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        $activeSeason = ExamSeason::where('semester_db_id', $activeSemester->db_id)
            ->where('is_active', true)
            ->first();

        if (!$activeSeason) {
            return response()->json(['data' => []]);
        }

        $exams = ExamPaper::with('course')
            ->where('exam_season_db_id', $activeSeason->db_id)
            ->where('program_db_id', $student->program_db_id)
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => $exams->map(fn (ExamPaper $paper) => [
                'public_id' => $paper->public_id,
                'course_code' => $paper->course?->code,
                'course_name' => $paper->course?->name ?? 'Unknown Course',
                'date' => $paper->date?->format('Y-m-d'),
                'time' => substr($paper->start_time, 0, 5),
                'location' => $paper->location,
                'duration' => $paper->duration_minutes . ' mins',
            ]),
        ]);
    }
}
