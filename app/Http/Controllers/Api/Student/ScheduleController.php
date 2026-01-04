<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // 1. Resolve Student (using the student relation on the User model)
        $user = Auth::user();
        $student = $user->profile()->with(['program.department'])->first();

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 2. Resolve Active Semester
        $activeSemester = Semester::active();
        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        // 3. Get IDs of courses the student should be attending this semester
        // This uses the currentCourses() logic from your Student model
        $currentCourseIds = $student->currentCourses()->pluck('courses.id');

        // 4. Fetch all relevant entries at once
        $scheduleData = TimetableEntry::query()
            ->with(['course', 'lecturer'])
            ->where('semester_id', $activeSemester->id)
            ->where('program_id', $student->program_id)
            ->whereIn('course_id', $currentCourseIds)
            ->orderBy('start_time')
            ->get();

        // 5. Define the Full Week Map (Matches your DB Enum order)
        $weekMap = [
            'MON' => 'Monday',
            'TUE' => 'Tuesday',
            'WED' => 'Wednesday',
            'THU' => 'Thursday',
            'FRI' => 'Friday',
            'SAT' => 'Saturday',
            'SUN' => 'Sunday',
        ];

        // 6. Format the response for a Weekly View UI
        $formattedWeeklySchedule = collect($weekMap)->map(function ($dayName, $shortCode) use ($scheduleData) {
            // Filter the fetched collection for this specific day
            $classesForDay = $scheduleData->where('day', $shortCode)->map(function ($entry) {
                return [
                    'public_id'    => $entry->public_id,
                    'start_time'   => $this->formatTime($entry->start_time),
                    'end_time'     => $this->formatTime($entry->end_time),
                    'course_code'  => $entry->course->code,
                    'course_name'  => $entry->course->name,
                    'location'     => $entry->location ?? 'Room TBA',
                    'lecturer_name' => $entry->lecturer
                        ? "{$entry->lecturer->first_name} {$entry->lecturer->last_name}"
                        : 'Staff',
                    'color_hex'    => $this->getDayColor($entry->day),
                ];
            })->values();

            return [
                'day_name' => $dayName,
                'is_free_day' => $classesForDay->isEmpty(),
                'classes' => $classesForDay
            ];
        })->values();

        return response()->json(['data' => $formattedWeeklySchedule]);
    }

    private function formatTime($time)
    {
        return $time ? date('H:i', strtotime($time)) : '00:00';
    }

    private function getDayColor($shortDay)
    {
        $colors = [
            'MON' => '#4CAF50', // Green
            'TUE' => '#2196F3', // Blue
            'WED' => '#FF9800', // Orange
            'THU' => '#9C27B0', // Purple
            'FRI' => '#F44336', // Red
        ];
        return $colors[$shortDay] ?? '#607D8B';
    }
}
