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

        // 1. Get Active Semester
        // Ensure your Semester model has a scopeActive() or similar logic
        $activeSemester = Semester::where('is_active', 'true')->first();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        // 2. Fetch Schedule
        // Since timetable_entries doesn't have lecturer_id, we check the related programCourse.
        $scheduleData = TimetableEntry::query()
            ->with(['programCourse.course']) // Eager load deep relationship
            ->where('semester_id', $activeSemester->id)
            ->whereHas('programCourse', function ($query) use ($lecturer) {
                // This assumes the 'program_courses' table has the 'lecturer_id'
                $query->where('lecturer_id', $lecturer->id);
            })
            ->orderBy('start_time')
            ->get();

        // 3. Define Day Mapping
        $dayMapping = [
            'MON' => 'Monday',
            'TUE' => 'Tuesday',
            'WED' => 'Wednesday',
            'THU' => 'Thursday',
            'FRI' => 'Friday',
            'SAT' => 'Saturday',
            'SUN' => 'Sunday',
        ];

        // 4. Transform Data
        $formattedSchedule = collect($dayMapping)->map(function ($dayName, $shortCode) use ($scheduleData) {

            // Filter entries for this specific day
            $classesForDay = $scheduleData->where('day', $shortCode)->map(function ($entry) {
                // Safe navigation to get course details
                $course = $entry->programCourse->course ?? null;

                return [
                    'start_time'  => $this->formatTime($entry->start_time),
                    'end_time'    => $this->formatTime($entry->end_time),
                    'course_code' => $course ? $course->code : 'N/A',
                    'course_name' => $course ? $course->name : 'Unknown',
                    'location'    => $entry->location ?? 'TBA',
                    // Use the short code (MON) to fetch color
                    'color_hex'   => $this->getDayColor($entry->day),
                ];
            })->values();

            return [
                'day_name'        => $dayName,
                // If no classes, it's a research/free day
                'is_research_day' => $classesForDay->isEmpty(),
                'classes'         => $classesForDay
            ];
        });

        return response()->json(['data' => $formattedSchedule->values()]);
    }

    /**
     * Helper to ensure time looks clean (e.g., 09:00)
     */
    private function formatTime($time)
    {
        return $time ? date('H:i', strtotime($time)) : '00:00';
    }

    /**
     * Returns a color hex based on the day short code (MON, TUE...)
     */
    private function getDayColor($shortDayCode)
    {
        $colors = [
            'MON' => '#4CAF50', // Green
            'TUE' => '#2196F3', // Blue
            'WED' => '#FF9800', // Orange
            'THU' => '#9C27B0', // Purple
            'FRI' => '#F44336', // Red
            'SAT' => '#607D8B', // Blue Grey
            'SUN' => '#607D8B', // Blue Grey
        ];

        return $colors[$shortDayCode] ?? '#9E9E9E'; // Default Grey
    }
}
