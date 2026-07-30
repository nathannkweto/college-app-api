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
     * NOTE: assumes `timetable_entries.day` stores 3-letter codes (MON, TUE, ...).
     * Same assumption as the Lecturer schedule — confirm against real data.
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

        // No enrollment table — a student's schedule is every timetable entry
        // for their program in the active semester.
        $entries = TimetableEntry::with(['course', 'lecturer'])
            ->where('semester_db_id', $activeSemester->db_id)
            ->where('program_db_id', $student->program_db_id)
            ->orderBy('start_time')
            ->get();

        $schedule = collect($this->dayMapping)->map(function ($dayName, $shortCode) use ($entries) {
            $classesForDay = $entries->where('day', $shortCode)->map(fn (TimetableEntry $entry) => [
                'public_id' => $entry->public_id,
                'start_time' => $this->formatTime($entry->start_time),
                'end_time' => $this->formatTime($entry->end_time),
                'course_code' => $entry->course?->code,
                'course_name' => $entry->course?->name ?? 'Unknown',
                'lecturer' => $entry->lecturer
                    ? trim($entry->lecturer->title . ' ' . $entry->lecturer->last_name)
                    : null,
                'location' => $entry->location ?? 'TBA',
            ])->values();

            return [
                'day_name' => $dayName,
                'is_free_day' => $classesForDay->isEmpty(),
                'classes' => $classesForDay,
            ];
        });

        return response()->json(['data' => $schedule->values()]);
    }

    private function formatTime($time): string
    {
        return $time ? date('H:i', strtotime($time)) : '00:00';
    }
}
