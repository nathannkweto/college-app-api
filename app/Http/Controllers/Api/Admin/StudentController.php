<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Program;
use App\Models\User;
use App\Models\ExamResult;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log; // Added Logger

class StudentController extends Controller
{
    protected $notifier;

    public function __construct(NotificationService $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * List Students.
     */
    public function index(Request $request)
    {
        $query = Student::with('program');

        // ... (Keep your search and filter logic here) ...

        $students = $query->paginate(20)->through(function ($student) {
            return [
                'public_id'    => $student->public_id,
                'student_id'   => $student->student_id,
                'first_name'   => $student->first_name,
                'last_name'    => $student->last_name,
                'email'        => $student->email,
                'gender'       => $student->gender,
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
     * Create a new Student.
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

        try {
            // Wrap transaction
            $registrationResult = DB::transaction(function () use ($validated) {
                $program = Program::where('public_id', $validated['program_public_id'])->firstOrFail();

                // A. Generate Custom Student ID
                $academicYear = date('Y', strtotime($validated['enrollment_date']));
                $code = $program->code ?? 'STU';
                $sequence = Student::where('program_id', $program->id)->count() + 1;
                $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);

                // B. Create User Login
                $user = User::create([
                    'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($studentId),
                    'role' => 'STUDENT',
                ]);

                // C. Create Student Profile
                $student = Student::create([
                    'user_id' => $user->id,
                    'public_id' => (string) Str::uuid(), // Explicitly generate UUID
                    'student_id' => $studentId,
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'program_id' => $program->id,
                    'gender' => $validated['gender'],
                    'national_id' => $validated['national_id'],
                    'current_semester_sequence' => 1,
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
            // Wrapped in try-catch so email failure doesn't crash the registration
            try {
                $this->notifier->sendWelcomeEmail(
                    $registrationResult['user'],
                    $registrationResult['student_id'],
                    'student'
                );
            } catch (\Exception $e) {
                Log::error("Email failed to send: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Student registered successfully',
                'student_id' => $registrationResult['student_id'],
            ], 201);

        } catch (\Exception $e) {
            // Log the full error for Cloud Run
            Log::error('Student Registration Failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Registration Failed',
                'message' => $e->getMessage(), // This will show you the REAL error
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }

    /**
     * Batch create students.
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

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->students as $studentData) {
                    $this->createStudent($studentData);
                }
            });
            return response()->json(['message' => 'Batch upload completed successfully.']);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Batch Upload Failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to create a single student.
     */
    private function createStudent($data)
    {
        $program = Program::where('public_id', $data['program_public_id'])->firstOrFail();

        // FIX: Correct date parsing syntax
        $academicYear = date('Y', strtotime($data['enrollment_date']));
        $code = $program->code ?? 'STU';
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
            'public_id' => (string) Str::uuid(), // Explicitly generate UUID
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

    // ... (Keep promotionPreview and promote methods exactly as they were) ...
    public function promotionPreview(Request $request)
    {
        // ... (Keep existing logic) ...
        $request->validate([
            'program_public_id' => 'required|exists:programs,public_id',
            'current_sequence' => 'required|integer',
        ]);

        $program = Program::where('public_id', $request->program_public_id)->first();
        $currentSeq = $request->current_sequence;
        $nextSeq = $currentSeq + 1;
        $isYearlyPromotion = ($currentSeq % 2 == 0);

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
            'details' => $details
        ]);
    }

    public function promote(Request $request)
    {
        // ... (Keep existing logic) ...
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
            $students = Student::where('program_id', $program->id)
                ->where('current_semester_sequence', $currentSeq)
                ->where('status', 'active')
                ->get();

            foreach ($students as $student) {
                $shouldPromote = true;

                if ($isYearlyPromotion) {
                    if ($this->hasFailuresInYear($student, $currentSeq)) {
                        $shouldPromote = false;
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

    private function hasFailuresInYear($student, $currentSeq)
    {
        $sequencesInYear = [$currentSeq - 1, $currentSeq];

        $requiredCourseIds = $student->program->courses()
            ->wherePivotIn('semester_sequence', $sequencesInYear)
            ->pluck('courses.id');

        if ($requiredCourseIds->isEmpty()) return false;

        $passedCourseIds = ExamResult::where('student_id', $student->id)
            ->whereIn('course_id', $requiredCourseIds)
            ->where('is_passed', true)
            ->pluck('course_id');

        return $passedCourseIds->count() < $requiredCourseIds->count();
    }
}
