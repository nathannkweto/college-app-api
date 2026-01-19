<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * List all courses assigned to the logged-in lecturer
     * for the CURRENT semester (based on parity).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $lecturer = $user->profile()->with('department')->first();

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        // 1. Get the Active Semester
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json(['message' => 'No active semester found'], 404);
        }

        // 2. Determine Active Sequences (Odd vs Even)
        $isOddSemester = ($activeSemester->semester_number % 2) !== 0;

        // 3. Fetch Assignments
        $assignedCourses = ProgramCourse::query()
            ->with([
                'course',
                'program.department',
                'program.qualification'
            ])
            ->where('lecturer_id', $lecturer->id)
            ->whereRaw('semester_sequence % 2 = ?', [$isOddSemester ? 1 : 0])
            ->get();

        // 4. Transform for API
        $data = $assignedCourses->map(function ($assignment) {
            return [
                'public_id' => $assignment->course->public_id,
                'course_name' => $assignment->course->name,
                'course_code' => $assignment->course->code,
                'program_name' => $assignment->program->name,
                'program_code' => $assignment->program->code,
                'semester_sequence' => $assignment->semester_sequence,
                'program_public_id' => $assignment->program->public_id,
            ];
        });

        return response()->json([
            'meta' => [
                'semester' => $activeSemester->name,
                'type' => $isOddSemester ? 'Odd (1, 3, 5...)' : 'Even (2, 4, 6...)'
            ],
            'data' => $data
        ]);
    }

    /**
     * Get details of a specific course assignment,
     * including the list of Students currently in that class.
     */
    public function show(Request $request, string $coursePublicId)
    {
        $user = Auth::user();
        $lecturer = $user->profile()->with('department')->first();

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        // 1. Validate Semester
        $activeSemester = Semester::where('is_active', true)->first();
        if (!$activeSemester) abort(404, 'No active semester');

        // 2. Find the specific assignment (ProgramCourse pivot)
        $assignment = ProgramCourse::query()
            ->where('lecturer_id', $lecturer->id)
            ->whereHas('course', function($q) use ($coursePublicId) {
                $q->where('public_id', $coursePublicId);
            })
            ->with(['course', 'program'])
            ->firstOrFail();

        // 3. Fetch Students
        // We eagerly load 'enrollments' to see if they already have a grade for this course
        $students = Student::query()
            ->where('program_id', $assignment->program_id)
            ->where('current_semester_sequence', $assignment->semester_sequence)
            ->with(['enrollments' => function($q) use ($assignment) {
                // Only get enrollments for THIS specific program course
                $q->where('program_course_id', $assignment->id);
            }])
            ->orderBy('last_name')
            ->get();

        return response()->json([
            // --- CRITICAL FIX: The Integer ID required by Flutter ---
            'program_course_id' => $assignment->id,
            // --------------------------------------------------------

            'course' => [
                'name' => $assignment->course->name,
                'code' => $assignment->course->code,
                'description' => 'Course description placeholder...',
            ],
            'program' => [
                'name' => $assignment->program->name,
                'code' => $assignment->program->code,
            ],
            'context' => [
                // --- CRITICAL FIX: The Semester Name required by Flutter ---
                'semester' => $activeSemester->name,
                'semester_sequence' => $assignment->semester_sequence,
                'student_count' => $students->count(),
            ],
            'students' => $students->map(function($student) {
                // Check if an enrollment exists (grade already submitted)
                $enrollment = $student->enrollments->first();

                return [
                    'public_id' => $student->public_id,
                    'student_id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'avatar' => $student->avatar_url,

                    // Map existing grades if they exist
                    'current_grade' => $enrollment,
                    'current_status' => $enrollment ? 'PASS' : 'PENDING', // Simplified logic
                ];
            })
        ]);
    }
}
