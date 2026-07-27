<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * NOTE: assumes `timetable_entries.day` stores 3-letter codes (MON, TUE, ...).
     * If your data actually stores full names ("Monday") or numbers (0-6),
     * this mapping needs adjusting — worth confirming against real rows.
     */
    private array $dayMapping = [
        'MON' => 'Monday',
        'TUE' => 'Tuesday',
        'WED' => 'Wednesday',
        'THU' => 'Thursday',
        'FRI' => 'Friday',
        'SAT' => 'Saturday',
        'SUN' => 'Sunday',
    ];

    public function index(Request $request)
    {
        $lecturer = Auth::user()->profile;

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        $activeSemester = Semester::getActive();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        $entries = TimetableEntry::with(['course', 'program'])
            ->where('semester_db_id', $activeSemester->db_id)
            ->where('lecturer_db_id', $lecturer->db_id)
            ->orderBy('start_time')
            ->get();

        $schedule = collect($this->dayMapping)->map(function ($dayName, $shortCode) use ($entries) {
            $classesForDay = $entries->where('day', $shortCode)->map(fn (TimetableEntry $entry) => [
                'public_id' => $entry->public_id,
                'start_time' => $this->formatTime($entry->start_time),
                'end_time' => $this->formatTime($entry->end_time),
                'course_code' => $entry->course?->code,
                'course_name' => $entry->course?->name ?? 'Unknown',
                'program_name' => $entry->program?->name,
                'location' => $entry->location ?? 'TBA',
                'color_hex' => $this->getDayColor($shortCode),
            ])->values();

            return [
                'day_name' => $dayName,
                'is_research_day' => $classesForDay->isEmpty(),
                'classes' => $classesForDay,
            ];
        });

        return response()->json(['data' => $schedule->values()]);
    }

    private function formatTime($time): string
    {
        return $time ? date('H:i', strtotime($time)) : '00:00';
    }

    private function getDayColor(string $shortDayCode): string
    {
        return [
            'MON' => '#4CAF50',
            'TUE' => '#2196F3',
            'WED' => '#FF9800',
            'THU' => '#9C27B0',
            'FRI' => '#F44336',
            'SAT' => '#607D8B',
            'SUN' => '#607D8B',
        ][$shortDayCode] ?? '#9E9E9E';
    }
}
