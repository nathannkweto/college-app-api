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
    public function index(Request $request) // Ensure Request is injected if you filter by params
    {
        // 1. Get IDs from Request (if passed as query params)
        // Note: Your route definition uses query params, so we should use them
        $semesterId = $request->query('semester_public_id');
        $programId = $request->query('program_public_id');

        // Resolve UUIDs if necessary, or just use the Active Semester logic you had
        $activeSemester = Semester::active();

        if (!$activeSemester) {
            return response()->json(['data' => []]);
        }

        // 2. Query the entries
        $query = TimetableEntry::with(['course', 'lecturer'])
            ->where('semester_id', $activeSemester->id)
            ->orderBy('day')
            ->orderBy('start_time');

        // OPTIONAL: Filter by Program if provided
        if ($programId) {
            $program = \App\Models\Program::where('public_id', $programId)->first();
            if ($program) {
                $query->where('program_id', $program->id);
            }
        }

        $entries = $query->get(); // <--- REMOVE ->groupBy('day')

        // 3. Return in 'data' key to match OpenAPI Schema
        return response()->json([
            'data' => $entries
        ]);
    }

    /**
     * Create a new timetable entry.
     */
    public function store(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id', // Add this validation
            'course_public_id' => 'required|exists:courses,public_id',
            'lecturer_public_id' => 'required|exists:lecturers,public_id',
            'day' => 'required|string|in:MON,TUE,WED,THU,FRI,SAT,SUN',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'required|string',
        ]);

        $activeSemester = Semester::active();
        if (!$activeSemester) {
            return response()->json(['message' => 'Cannot create timetable without an active semester.'], 400);
        }

        // 1. Resolve ALL UUIDs to Internal IDs
        $program = \App\Models\Program::where('public_id', $request->program_public_id)->firstOrFail(); // <--- NEW
        $course = Course::where('public_id', $request->course_public_id)->first();
        $lecturer = Lecturer::where('public_id', $request->lecturer_public_id)->first();

        // 2. Conflict Checks (unchanged)
        if ($this->hasConflict($activeSemester->id, 'lecturer_id', $lecturer->id, $request)) {
            return response()->json(['message' => 'Lecturer is already teaching at this time.'], 409);
        }
        if ($this->hasConflict($activeSemester->id, 'location', $request->location, $request)) {
            return response()->json(['message' => 'Room is already booked at this time.'], 409);
        }

        // 3. Create Entry (Include program_id)
        $entry = TimetableEntry::create([
            'semester_id' => $activeSemester->id,
            'program_id'  => $program->id, // <--- THIS WAS MISSING
            'course_id'   => $course->id,
            'lecturer_id' => $lecturer->id,
            'day'         => $request->day,
            'start_time'  => $request->start_time,
            'end_time'    => $request->end_time,
            'location'    => $request->location,
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
