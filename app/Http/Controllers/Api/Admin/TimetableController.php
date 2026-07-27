<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use App\Models\Semester;
use App\Models\Program;
use App\Models\Course;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TimetableController extends Controller
{
    /**
     * GET /api/v1/admin/logistics/timetable
     */
    public function index(Request $request)
    {
        $request->validate([
            'semester_public_id' => 'required|string',
            'program_public_id'  => 'nullable|string',
        ]);

        $semester = Semester::where('public_id', $request->semester_public_id)->firstOrFail();

        $query = TimetableEntry::with(['course', 'lecturer', 'program', 'semester'])
            ->where('semester_db_id', $semester->db_id);

        if ($request->filled('program_public_id')) {
            $program = Program::where('public_id', $request->program_public_id)->firstOrFail();
            $query->where('program_db_id', $program->db_id);
        }

        $entries = $query->orderBy('day')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => $entries
        ]);
    }

    /**
     * POST /api/v1/admin/logistics/timetable
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_public_id'  => 'required|string|exists:semesters,public_id',
            'program_public_id'   => 'nullable|string|exists:programs,public_id',
            'course_public_id'    => 'required|string|exists:courses,public_id',
            'lecturer_public_id'  => 'nullable|string|exists:lecturers,public_id',
            'day'                 => 'required|string',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'location'            => 'nullable|string|max:255',
        ]);

        $semester = Semester::where('public_id', $validated['semester_public_id'])->firstOrFail();
        $course   = Course::where('public_id', $validated['course_public_id'])->firstOrFail();

        $program = null;
        if (!empty($validated['program_public_id'])) {
            $program = Program::where('public_id', $validated['program_public_id'])->firstOrFail();
        }

        $lecturer = null;
        if (!empty($validated['lecturer_public_id'])) {
            $lecturer = Lecturer::where('public_id', $validated['lecturer_public_id'])->firstOrFail();
        }

        $entry = TimetableEntry::create([
            'public_id'       => (string) Str::uuid(),
            'semester_db_id'  => $semester->db_id,
            'program_db_id'   => $program?->db_id,
            'course_db_id'    => $course->db_id,
            'lecturer_db_id'  => $lecturer?->db_id,
            'day'             => $validated['day'],
            'start_time'      => $validated['start_time'],
            'end_time'        => $validated['end_time'],
            'location'        => $validated['location'] ?? null,
        ]);

        $entry->load(['course', 'lecturer', 'program', 'semester']);

        return response()->json([
            'message' => 'Timetable entry created successfully',
            'data'    => $entry
        ], 201);
    }
}
