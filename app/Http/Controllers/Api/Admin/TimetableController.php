<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    /**
     * Get the master timetable for the active semester.
     */
    public function index()
    {
        $activeSemester = Semester::active();

        if (!$activeSemester) {
            return response()->json(['data' => []]); // Return empty if no semester
        }

        $entries = TimetableEntry::with(['course', 'lecturer'])
            ->where('semester_id', $activeSemester->id)
            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day');

        return response()->json([
            'semester' => $activeSemester->academic_year,
            'timetable' => $entries
        ]);
    }

    /**
     * Create a new timetable entry.
     */
    public function store(Request $request)
    {
        $request->validate([
            'course_public_id' => 'required|exists:courses,public_id',
            'lecturer_public_id' => 'required|exists:lecturers,public_id',
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i', // 09:00
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'required|string', // Room number
        ]);

        $activeSemester = Semester::active();
        if (!$activeSemester) {
            return response()->json(['message' => 'Cannot create timetable without an active semester.'], 400);
        }

        // Resolve UUIDs
        $course = Course::where('public_id', $request->course_public_id)->first();
        $lecturer = Lecturer::where('public_id', $request->lecturer_public_id)->first();

        // 1. Check if Lecturer is busy
        if ($this->hasConflict($activeSemester->id, 'lecturer_id', $lecturer->id, $request)) {
            return response()->json(['message' => 'Lecturer is already teaching another class at this time.'], 409);
        }

        // 2. Check if Room (Location) is occupied
        if ($this->hasConflict($activeSemester->id, 'location', $request->location, $request)) {
            return response()->json(['message' => 'Room is already booked at this time.'], 409);
        }

        // Create Entry
        $entry = TimetableEntry::create([
            'semester_id' => $activeSemester->id,
            'course_id' => $course->id,
            'lecturer_id' => $lecturer->id,
            'day' => $request->day,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'location' => $request->location,
        ]);

        return response()->json(['message' => 'Timetable entry created', 'data' => $entry], 201);
    }

    /**
     * Helper to check for time overlaps.
     * Logic: (StartA < EndB) and (EndA > StartB) indicates overlap.
     */
    private function hasConflict($semesterId, $field, $value, $request)
    {
        return TimetableEntry::where('semester_id', $semesterId)
            ->where('day', $request->day)
            ->where($field, $value)
            ->where(function ($query) use ($request) {
                $query->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();
    }
}
