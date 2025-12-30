<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * Get the timetable for the student's current courses.
     * Route: GET /api/v1/student/schedule
     */
    public function index(Request $request)
    {
        $student = Auth::user()->profile;

        // Find the active academic semester
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json(['message' => 'No active semester.'], 404);
        }

        // Get IDs of courses the student is taking now (Current + Carryovers)
        $courseIds = $student->currentCourses()->pluck('courses.id')
            ->merge($student->carryOverCourses()->pluck('courses.id'));

        // Fetch timetable entries for these courses in the active semester
        $schedule = TimetableEntry::with(['course', 'lecturer'])
            ->where('semester_id', $activeSemester->id)
            ->whereIn('course_id', $courseIds)
            ->orderBy('day')
            ->orderBy('start_time')
            ->get();

        // Group by day for the frontend (Monday, Tuesday...)
        return response()->json([
            'semester' => $activeSemester->academic_year . ' - Sem ' . $activeSemester->semester_number,
            'schedule' => $schedule->groupBy('day')
        ]);
    }
}
