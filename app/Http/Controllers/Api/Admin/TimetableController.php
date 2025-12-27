<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\StudentGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|in:MON,TUE,WED,THU,FRI,SAT',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'course_public_id' => 'required|exists:courses,public_id',
            'lecturer_public_id' => 'required|exists:lecturers,public_id',
            'location' => 'required|string',
            'group_letter' => 'required|string', // A, B, C
        ]);

        // Resolve IDs
        $courseId = Course::getIdFromPublicId($validated['course_public_id']);
        $lecturerId = Lecturer::getIdFromPublicId($validated['lecturer_public_id']);

        // Find or Create the Student Group (e.g. CS101 Group A)
        // Note: Logic simplifies assuming we attach group to Program.
        // For now, we fetch the course's department/program link or pass program_id in request.
        // To keep it simple, let's assume we pass program_public_id or resolve it from course context.
        // (Skipping deep group logic for brevity, using simple lookup)

        // --- COLLISION DETECTION ---

        // 1. Check Room Availability
        $roomClash = TimetableEntry::where('day', $validated['day'])
            ->where('location', $validated['location'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']]);
            })->exists();

        if ($roomClash) {
            throw ValidationException::withMessages([
                'location' => ['This room is already booked for this time slot.']
            ]);
        }

        // 2. Check Lecturer Availability
        $lecturerClash = TimetableEntry::where('day', $validated['day'])
            ->where('lecturer_id', $lecturerId)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']]);
            })->exists();

        if ($lecturerClash) {
            throw ValidationException::withMessages([
                'lecturer' => ['This lecturer is already teaching another class at this time.']
            ]);
        }

        // --- SAVE ---

        TimetableEntry::create([
            'day' => $validated['day'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'course_id' => $courseId,
            'lecturer_id' => $lecturerId,
            'location' => $validated['location'],
            'student_group_id' => 1 // Placeholder: You'd resolve the Group ID here
        ]);

        return response()->json(['message' => 'Class scheduled successfully'], 201);
    }
}
