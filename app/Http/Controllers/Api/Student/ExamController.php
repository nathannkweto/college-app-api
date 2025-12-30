<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    /**
     * Get upcoming exam schedule.
     * Route: GET /api/v1/student/exams/upcoming
     */
    public function upcoming(Request $request)
    {
        $student = Auth::user()->profile;
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester || !$student) {
            return response()->json(['data' => []]);
        }

        $courseIds = $student->currentCourses()->pluck('courses.id');

        $exams = ExamPaper::whereHas('examSeason', function ($q) use ($activeSemester) {
            $q->where('semester_id', $activeSemester->id);
        })
            ->whereIn('course_id', $courseIds)
            ->with('course')
            ->where('date', '>=', now()->toImmutable()->startOfDay())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($paper) {
                return [
                    'course'     => $paper->course?->name ?? 'Unknown Course',
                    'code'       => $paper->course?->code ?? 'N/A',
                    'date'       => $paper->date->format('Y-m-d'),
                    'start_time' => substr($paper->start_time, 0, 5), // Changes "09:00:00" to "09:00"
                    'location'   => $paper->location,
                    'duration'   => $paper->duration_minutes . ' mins'
                ];
            });

        return response()->json(['data' => $exams]);
    }
}
