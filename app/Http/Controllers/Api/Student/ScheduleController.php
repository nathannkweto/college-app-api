<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // 1. Resolve Student
        $student = Auth::user()->profile;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 2. Resolve Active Semester
        $activeSemester = Semester::where('is_active', 'true')->first();
        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        // 3. Get IDs of ProgramCourses (Pivot IDs) the student is enrolled in.
        // We use 'program_course_id' to link Enrollment -> TimetableEntry precisely.
        $enrolledPivotIds = Enrollment::where('student_id', $student->id)
            ->where('semester_id', $activeSemester->id)
            ->pluck('program_course_id')
            ->unique()
            ->toArray();

        // 4. Fetch Timetable Entries
        // We filter by the Pivot IDs we found above.
        $scheduleData = TimetableEntry::query()
            // FIX: Eager load nested relationships
            ->with(['programCourse.course', 'programCourse.lecturer'])
            ->where('semester_id', $activeSemester->id)
            // FIX: Filter by the pivot ID column, not course_id
            ->whereIn('program_course_id', $enrolledPivotIds)
            // OPTIONAL: Sort by day for database efficiency before collection mapping
            ->orderByRaw("CASE day
            WHEN 'MON' THEN 1
            WHEN 'TUE' THEN 2
            WHEN 'WED' THEN 3
            WHEN 'THU' THEN 4
            WHEN 'FRI' THEN 5
            WHEN 'SAT' THEN 6
            WHEN 'SUN' THEN 7
            ELSE 8 END")
            ->orderBy('start_time')
            ->get();

        // 5. Define Week Map
        $weekMap = [
            'MON' => 'Monday', 'TUE' => 'Tuesday', 'WED' => 'Wednesday',
            'THU' => 'Thursday', 'FRI' => 'Friday', 'SAT' => 'Saturday', 'SUN' => 'Sunday',
        ];

        // 6. Format Response
        $formattedWeeklySchedule = collect($weekMap)->map(function ($dayName, $shortCode) use ($scheduleData) {

            $classesForDay = $scheduleData->where('day', $shortCode)->map(function ($entry) {
                // FIX: Access nested data safely
                $pCourse = $entry->programCourse;
                $course = $pCourse ? $pCourse->course : null;
                $lecturer = $pCourse ? $pCourse->lecturer : null;

                return [
                    'public_id'     => $entry->public_id,
                    'start_time'    => $this->formatTime($entry->start_time),
                    'end_time'      => $this->formatTime($entry->end_time),
                    'course_code'   => $course ? $course->code : 'N/A',
                    'course_name'   => $course ? $course->name : 'Unknown',
                    'location'      => $entry->location ?? 'Room TBA',
                    // FIX: Construct the string expected by Frontend
                    'lecturer_name' => $lecturer
                        ? "{$lecturer->first_name} {$lecturer->last_name}"
                        : 'Staff',
                    'color_hex'     => $this->getDayColor($entry->day),
                ];
            })->values();

            return [
                'day_name'    => $dayName,
                'is_free_day' => $classesForDay->isEmpty(),
                'classes'     => $classesForDay
            ];
        })->values();

        return response()->json(['data' => $formattedWeeklySchedule]);
    }

// Helper methods remain the same...
    private function formatTime($time)
    {
        return $time ? date('H:i', strtotime($time)) : '00:00';
    }

    private function getDayColor($shortDay)
    {
        $colors = [
            'MON' => '#4CAF50',
            'TUE' => '#2196F3',
            'WED' => '#FF9800',
            'THU' => '#9C27B0',
            'FRI' => '#F44336',
        ];
        return $colors[$shortDay] ?? '#607D8B';
    }
}
