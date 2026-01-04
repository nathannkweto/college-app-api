<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $lecturer = Auth::user()->profile;

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        $activeSemester = Semester::active();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        $scheduleData = TimetableEntry::query()
            ->with(['course'])
            ->where('lecturer_id', $lecturer->id)
            ->where('semester_id', $activeSemester->id)
            ->orderBy('start_time')
            ->get();

        $dayMapping = [
            'MON' => 'Monday',
            'TUE' => 'Tuesday',
            'WED' => 'Wednesday',
            'THU' => 'Thursday',
            'FRI' => 'Friday',
            'SAT' => 'Saturday',
            'SUN' => 'Sunday',
        ];
        // ADDED: 'use ($scheduleData)' allows the closure to access the results
        $formattedSchedule = collect($dayMapping)->map(function ($dayName, $shortCode) use ($scheduleData) {
            $classesForDay = $scheduleData->where('day', $shortCode)->map(function ($entry) {
                return [
                    'start_time' => $this->formatTime($entry->start_time),
                    'end_time'   => $this->formatTime($entry->end_time),
                    'course_code' => $entry->course->code ?? 'N/A',
                    'course_name' => $entry->course->name ?? 'Unknown',
                    'location'    => $entry->location ?? 'TBA',
                    'color_hex'   => $this->getDayColor($entry->day),
                ];
            })->values();

            return [
                'day_name' => $dayName,
                'is_research_day' => $classesForDay->isEmpty(),
                'classes' => $classesForDay
            ];
        });

        return response()->json(['data' => $formattedSchedule]);
    }

    /**
     * Helper to ensure time looks clean (e.g., 09:00)
     */
    private function formatTime($time)
    {
        return $time ? date('H:i', strtotime($time)) : '00:00';
    }

    /**
     * Optional: Helper to provide distinct colors for the UI based on day
     */
    private function getDayColor($day)
    {
        $colors = [
            'Monday'    => '#4CAF50', // Green
            'Tuesday'   => '#2196F3', // Blue
            'Wednesday' => '#FF9800', // Orange
            'Thursday'  => '#9C27B0', // Purple
            'Friday'    => '#F44336', // Red
        ];

        return $colors[$day] ?? '#607D8B'; // Default Slate
    }
}
