<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $lecturer = Auth::user()->profile;
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        $scheduleData = $lecturer->timetableEntries()
            ->with(['course'])
            ->where('semester_id', $activeSemester->id)
            ->get();

        // ALIGNMENT: Convert grouped map to a List of DailySchedule
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $formattedSchedule = collect($days)->map(function ($day) use ($scheduleData) {
            $classesForDay = $scheduleData->where('day', $day)->map(function ($entry) {
                return [
                    'start_time' => $entry->start_time,
                    'end_time' => $entry->end_time,
                    'course_code' => $entry->course->code,
                    'course_name' => $entry->course->name,
                    'location' => $entry->location ?? 'TBA',
                    'color_hex' => '#4CAF50',
                ];
            })->values();

            return [
                'day_name' => $day,
                'is_research_day' => $classesForDay->isEmpty(),
                'classes' => $classesForDay
            ];
        });

        return response()->json(['data' => $formattedSchedule]);
    }
}
