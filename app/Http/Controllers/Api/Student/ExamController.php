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
     * Get upcoming exam schedule.
     */
    public function upcoming(Request $request)
    {
        // 1. Get Student Profile
        $user = Auth::user();
        $student = $user->profile()->with(['program.department'])->first();

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 2. Get Active Semester
        $activeSemester = Semester::active(); // Uses your helper
        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        // 3. Get the Active Exam Season for this Semester
        // We only want to show exams if the season is actually declared/active
        $activeSeason = ExamSeason::where('semester_id', $activeSemester->id)
            ->where('is_active', true)
            ->first();

        if (!$activeSeason) {
            return response()->json(['data' => []]);
        }

        // 4. Get the 'program_course_id's relevant to the student
        // The student is in a specific program and specific sequence.
        // We need the ID of the pivot table row (program_course.id),
        // because ExamPaper links to THAT, not just the course_id.
        $relevantProgramCourseIds = \DB::table('program_course')
            ->where('program_id', $student->program_id)
            ->where('semester_sequence', $student->current_semester_sequence)
            ->pluck('id');

        // 5. Query Exam Papers
        $exams = ExamPaper::query()
            ->with(['programCourse.course']) // Nested Eager Loading to get Course Name
            ->where('exam_season_id', $activeSeason->id)
            ->whereIn('program_course_id', $relevantProgramCourseIds)
            // Optional: Only show future exams?
            // ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($paper) {
                // Access the nested course object safely
                $course = $paper->programCourse->course ?? null;

                return [
                    'public_id'  => $paper->public_id,
                    'title'      => $course ? $course->name : 'Unknown Course',
                    'code'       => $course ? $course->code : 'N/A',
                    'date'       => $paper->date->format('Y-m-d'),
                    'time'       => $this->formatTime($paper->start_time),
                    'location'   => $paper->location,
                    'duration'   => $paper->duration_minutes . ' mins'
                ];
            });

        return response()->json(['data' => $exams]);
    }

    private function formatTime($time)
    {
        return substr($time, 0, 5); // Returns "09:00" from "09:00:00"
    }
}
