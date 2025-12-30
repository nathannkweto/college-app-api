<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Program;
use App\Models\User;
use App\Models\ExamResult;
use App\Services\NotificationService; // Import the Service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    protected $notifier;

    // Inject the NotificationService
    public function __construct(NotificationService $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * List Students with pagination and search.
     */
    public function index(Request $request)
    {
        $query = Student::with('program'); // Ensure program is eager loaded

        // ... (Keep your search and filter logic here) ...

        $students = $query->paginate(20)->through(function ($student) {
            return [
                'public_id'    => $student->public_id,
                'student_id'   => $student->student_id,
                // 🔴 FIX: Send separate first/last names (Snake Case)
                'first_name'   => $student->first_name,
                'last_name'    => $student->last_name,
                'email'        => $student->email,
                'gender'       => $student->gender, // Ensure "M" or "F"

                // 🔴 FIX: Send a Program Object, not a string
                'program'      => [
                    'public_id' => $student->program->public_id ?? '',
                    'name'      => $student->program->name ?? 'N/A',
                    'code'      => $student->program->code ?? '',
                ],

                'current_semester_sequence' => $student->current_semester_sequence,
                'status' => $student->status,
            ];
        });

        return response()->json($students);
    }

    /**
     * Create a new Student and their Login Account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'program_public_id' => 'required|exists:programs,public_id',
            'gender' => 'required|in:M,F',
            'national_id' => 'required|string|unique:students,national_id',
            'enrollment_date' => 'required|date',
            'dob' => 'required|date',
            'address' => 'required|string',
            'phone' => 'required|string',
        ]);

        // Wrap transaction
        $registrationResult = DB::transaction(function () use ($validated) {
            $program = Program::where('public_id', $validated['program_public_id'])->firstOrFail();

            // A. Generate Custom Student ID (e.g. 2025-CS-001)
            $academicYear = date('Y', strtotime($validated['enrollment_date']));
            $code = $program->code ?? 'STU'; // Fallback if no code
            $sequence = Student::where('program_id', $program->id)->count() + 1;
            $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);

            // B. Create User Login (Password = Student ID)
            $user = User::create([
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($studentId), // Default password is ID
                'role' => 'STUDENT',
            ]);

            // C. Create Student Profile
            $student = Student::create([
                'user_id' => $user->id,
                'student_id' => $studentId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'program_id' => $program->id,
                'gender' => $validated['gender'],
                'national_id' => $validated['national_id'],
                'current_semester_sequence' => 1, // Start at Year 1
                'status' => 'active',
                'enrollment_date' => $validated['enrollment_date'],
                'dob' => $validated['dob'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
            ]);

            return [
                'user' => $user,
                'student_id' => $studentId,
                'password' => $studentId
            ];
        });

        // D. Send Welcome Email (Outside Transaction)
        $this->notifier->sendWelcomeEmail(
            $registrationResult['user'],
            $registrationResult['student_id'],
            'student'
        );

        return response()->json([
            'message' => 'Student registered successfully',
            'student_id' => $registrationResult['student_id'],
        ], 201);
    }

    /**
     * Batch create students from a JSON array (or CSV parsed on frontend).
     */
    public function batchUpload(Request $request)
    {
        $request->validate([
            'students' => 'required|array',
            'students.*.first_name' => 'required',
            'students.*.last_name' => 'required',
            'students.*.email' => 'required|email|unique:users,email',
            'students.*.gender' => 'required|in:M,F',
            'program_public_id' => 'required|exists:programs,public_id',
            'enrollment_date' => 'required|date',
            'dob' => 'required|date',
            'address' => 'required|string',
            'phone' => 'required|string',

        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->students as $studentData) {
                /**
                 * TODO: In a real app, you might want to wrap this in a try-catch
                 *  to report which specific rows failed.
                 */
                $this->createStudent($studentData);
            }
        });

        return response()->json(['message' => 'Batch upload completed successfully.']);
    }

    /**
     * Helper to create a single student (used by store and batchUpload)
     */
    private function createStudent($data)
    {
        $program = Program::where('public_id', $data['program_public_id'])->firstOrFail();

        // Ideally pass this in, or generate inside
        $academicYear = date('Y', ['enrollment_date']);
        $code = $program->code ?? 'STU'; // Fallback if no code
        $sequence = Student::where('program_id', $program->id)->count() + 1;
        $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);

        $user = User::create([
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($studentId),
            'role' => 'STUDENT',
        ]);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => $studentId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'gender' => $data['gender'],
            'program_id' => $program->id,
            'enrollment_date' => $data['enrollment_date'],
            'current_semester_sequence' => 1,
            'status' => 'active',
            'national_id' => $data['national_id'],
            'dob' => $data['dob'],
            'address' => $data['address'],
            'phone' => $data['phone'],

        ]);
    }

    /**
     * Preview promotion to see who qualifies.
     */
    public function promotionPreview(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id',
            'current_sequence' => 'required|integer',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->first();
        $currentSeq = $request->current_sequence;
        $nextSeq = $currentSeq + 1;

        // 1. Determine type of Promotion
        // If current sequence is even (2, 4, 6), we are finishing a Year.
        // If current sequence is odd (1, 3, 5), we are just moving to next semester.
        $isYearlyPromotion = ($currentSeq % 2 == 0);

        // 2. Get Candidates
        $students = Student::where('program_id', $program->id)
            ->where('current_semester_sequence', $currentSeq)
            ->where('status', 'active')
            ->get();

        $eligibleCount = 0;
        $detainedCount = 0;
        $details = [];

        foreach ($students as $student) {
            $status = 'PROMOTED';
            $reason = 'Automatic semester progression';

            if ($isYearlyPromotion) {
                // Check if they passed everything in the current YEAR (e.g. Seq 1 and 2)
                $hasFailures = $this->hasFailuresInYear($student, $currentSeq);

                if ($hasFailures) {
                    $status = 'DETAINED';
                    $reason = 'Failed courses in current year';
                    $detainedCount++;
                } else {
                    $reason = 'Passed all requirements';
                    $eligibleCount++;
                }
            } else {
                $eligibleCount++;
            }

            $details[] = [
                'name' => $student->first_name . ' ' . $student->last_name,
                'student_id' => $student->student_id,
                'status' => $status,
                'reason' => $reason
            ];
        }

        return response()->json([
            'program' => $program->name,
            'promotion_type' => $isYearlyPromotion ? 'YEARLY TRANSITION (Strict)' : 'SEMESTER PROGRESSION (Automatic)',
            'moving_to_sequence' => $nextSeq,
            'stats' => [
                'total_candidates' => $students->count(),
                'eligible' => $eligibleCount,
                'detained' => $detainedCount
            ],
            'details' => $details // Frontend can show a list of who will fail
        ]);
    }

    /**
     * Execute the Promotion.
     */
    public function promote(Request $request)
    {
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id',
            'current_sequence' => 'required|integer',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->first();
        $currentSeq = $request->current_sequence;
        $nextSeq = $currentSeq + 1;
        $isYearlyPromotion = ($currentSeq % 2 == 0);
        $isGraduating = $nextSeq > $program->total_semesters;

        DB::transaction(function () use ($program, $currentSeq, $nextSeq, $isYearlyPromotion, $isGraduating) {
            // Get all students in this batch
            $students = Student::where('program_id', $program->id)
                ->where('current_semester_sequence', $currentSeq)
                ->where('status', 'active')
                ->get();

            foreach ($students as $student) {
                // Logic:
                // 1. If mid-year (1->2), everyone moves.
                // 2. If year-end (2->3), only those with NO failures move.

                $shouldPromote = true;

                if ($isYearlyPromotion) {
                    if ($this->hasFailuresInYear($student, $currentSeq)) {
                        $shouldPromote = false;
                        // Optional: Mark student status as 'repeating' if you want
                        // $student->update(['status' => 'repeating']);
                    }
                }

                if ($shouldPromote) {
                    if ($isGraduating) {
                        $student->update([
                            'status' => 'graduated',
                            'current_semester_sequence' => $program->total_semesters
                        ]);
                    } else {
                        $student->update([
                            'current_semester_sequence' => $nextSeq
                        ]);
                    }
                }
            }
        });

        return response()->json(['message' => 'Promotion process completed.']);
    }

    /**
     * Helper: Check if student failed courses in the academic year ending at $currentSeq.
     * e.g., If finishing Seq 2, check failures in Seq 1 and Seq 2.
     */
    private function hasFailuresInYear($student, $currentSeq)
    {
        // Identify the sequences in this year.
        // If current is 2, year is (1, 2). If current is 4, year is (3, 4).
        $sequencesInYear = [$currentSeq - 1, $currentSeq];

        // 1. Get Course IDs attached to these sequences for this Program
        $requiredCourseIds = $student->program->courses()
            ->wherePivotIn('semester_sequence', $sequencesInYear)
            ->pluck('courses.id');

        if ($requiredCourseIds->isEmpty()) return false; // No courses? No failure.

        // 2. Check Exam Results
        // We need to see if ANY of these required courses are NOT passed.

        // Get IDs of courses the student has explicitly PASSED
        $passedCourseIds = ExamResult::where('student_id', $student->id)
            ->whereIn('course_id', $requiredCourseIds)
            ->where('is_passed', true)
            ->pluck('course_id');

        // If the number of passed courses < number of required courses, they failed something.
        // (This assumes 100% pass rate required. You can adjust logic for "Carryovers" here)
        return $passedCourseIds->count() < $requiredCourseIds->count();
    }
}
