<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TimetableController extends Controller
{
    /**
     * Get the master timetable for the active semester.
     */
    public function index(Request $request)
    {
        $semesterId = $request->query('semester_public_id');
        if (!$semesterId) {
            return response()->json(['message' => 'semester_public_id is required'], 400);
        }

        $semester = Semester::where('public_id', $semesterId)->first();

        if (!$semester) {
            return response()->json(['data' => []]);
        }

        // 1. Build Query
        $query = TimetableEntry::where('semester_id', $semester->id)
            ->with(['programCourse.course', 'programCourse.lecturer']);

        // 2. Cross-Database Compatible Sorting (SQLite & MySQL)
        $query->orderByRaw("
        CASE day
            WHEN 'MON' THEN 1
            WHEN 'TUE' THEN 2
            WHEN 'WED' THEN 3
            WHEN 'THU' THEN 4
            WHEN 'FRI' THEN 5
            WHEN 'SAT' THEN 6
            WHEN 'SUN' THEN 7
            ELSE 8
        END
    ")->orderBy('start_time');

        // 3. Filter by Program
        $programId = $request->query('program_public_id');
        if ($programId) {
            $program = Program::where('public_id', $programId)->first();
            if ($program) {
                $query->whereHas('programCourse', function($q) use ($program) {
                    $q->where('program_id', $program->id);
                });
            }
        }

        $entries = $query->get();

        // 4. Transform Response (Flatten nested relations)
        $data = $entries->map(function ($entry) {
            return [
                'public_id'  => $entry->public_id,
                'day'        => $entry->day,
                'start_time' => substr($entry->start_time, 0, 5),
                'end_time'   => substr($entry->end_time, 0, 5),
                'location'   => $entry->location,
                'course'     => $entry->programCourse ? $entry->programCourse->course : null,
                'lecturer'   => $entry->programCourse ? $entry->programCourse->lecturer : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'semester_public_id' => 'required|exists:semesters,public_id',
            'program_public_id'  => 'required|exists:programs,public_id',
            'course_public_id'   => 'required|exists:courses,public_id',
            'day'                => 'required|in:MON,TUE,WED,THU,FRI,SAT,SUN',
            'start_time'         => 'required|date_format:H:i',
            'end_time'           => 'required|date_format:H:i|after:start_time',
            'location'           => 'required|string',
        ]);

        $activeSemester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        // 1. Resolve Program & Course to find the Pivot
        $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
        $course = Course::where('public_id', $request->course_public_id)->firstOrFail();

        // 2. Find the Assignment (Pivot)
        // We assume the lecturer is assigned HERE
        $programCourse = ProgramCourse::where('program_id', $program->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$programCourse) {
            return response()->json(['message' => 'This course is not assigned to this program.'], 422);
        }

        if (!$programCourse->lecturer_id) {
            return response()->json(['message' => 'No lecturer is assigned to this course yet. Please assign a lecturer in the curriculum settings first.'], 422);
        }

        // 3. Conflict Check: Lecturer
        // We check the lecturer found in the PIVOT, not from the request
        if ($this->hasConflict($activeSemester->id, 'lecturer_id', $programCourse->lecturer_id, $request)) {
            return response()->json(['message' => 'The assigned lecturer is already teaching at this time.'], 409);
        }

        // 4. Conflict Check: Location
        if ($this->hasConflict($activeSemester->id, 'location', $request->location, $request)) {
            return response()->json(['message' => 'Room is already booked at this time.'], 409);
        }

        // 5. Create Entry
        // We ONLY save the program_course_id (plus time/loc)
        $entry = TimetableEntry::create([
            'semester_id'       => $activeSemester->id,
            'program_course_id' => $programCourse->id,
            'day'               => $request->day,
            'start_time'        => $request->start_time,
            'end_time'          => $request->end_time,
            'location'          => $request->location,
            'public_id'         => (string) Str::uuid(),
        ]);

        // Reload relations so the response matches the Spec
        $entry->load(['programCourse.course', 'programCourse.lecturer']);

        return response()->json(['message' => 'Timetable entry created', 'data' => $entry], 201);
    }

// Helper for conflict logic needs a slight update to handle lecturer check
// Since lecturer_id is NOT in timetable_entries, we must join the pivot table to check it
    protected function hasConflict($semesterId, $type, $value, $request)
    {
        $query = TimetableEntry::where('semester_id', $semesterId)
            ->where('day', $request->day)
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('start_time', '<=', $request->start_time)
                            ->where('end_time', '>=', $request->end_time);
                    });
            });

        if ($type === 'location') {
            $query->where('location', $value);
        }
        elseif ($type === 'lecturer_id') {
            // COMPLEX: Check if any entry in this timeslot belongs to a program_course
            // that has THIS lecturer assigned.
            $query->whereHas('programCourse', function ($q) use ($value) {
                $q->where('lecturer_id', $value);
            });
        }

        return $query->exists();
    }
}
