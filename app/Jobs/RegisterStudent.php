<?php

namespace App\Jobs;

use App\Models\Program;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Str;
use App\Services\NotificationService;

class RegisterStudent implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(NotificationService $notifier)
    {
        if ($this->batch()?->cancelled()) return;

        // 1. Check existence first
        $exists = Student::where('email', $this->data['email'])
            ->orWhere('national_id', $this->data['nrc_number'])
            ->exists();

        if ($exists) {
            return;
        }

        // 2. Fetch Program OUTSIDE the transaction first to ensure it exists.
        // This validates the data before we ever touch a transaction.
        $program = Program::where('code', $this->data['program_code'])->first();

        if (!$program) {
            // Fails the job clearly without db errors
            $this->fail(new \Exception("Program {$this->data['program_code']} not found"));
            return;
        }

        $user = null;
        $studentId = null;

        // 3. Start Transaction
        DB::transaction(function () use ($program, &$user, &$studentId) {

            // 4. THE FIX: Use Postgres Advisory Lock.
            // We lock based on the Program ID.
            // 'pg_advisory_xact_lock' automatically releases when this transaction commits or rolls back.
            // hashtext() turns the string key into the required integer for the lock.
            $lockKey = "student_registration_lock_" . $program->id;
            DB::statement("SELECT pg_advisory_xact_lock(hashtext(?))", [$lockKey]);

            // 5. ID Generation (Safe because we hold the advisory lock)
            $academicYear = date('Y', strtotime($this->data['enrollment_date']));
            $code = $program->code ?? 'STU';

            $sequence = Student::where('program_id', $program->id)->count() + 1;
            $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);

            while(Student::where('student_id', $studentId)->exists()) {
                $sequence++;
                $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);
            }

            // 6. Create User
            $user = User::firstOrCreate(
                ['email' => $this->data['email']],
                [
                    'name' => $this->data['first_name'] . ' ' . $this->data['last_name'],
                    'password' => Hash::make($studentId),
                    'role' => 'STUDENT',
                ]
            );

            // 7. Create Student
            Student::create(
                [
                    'email' => $this->data['email'],
                    'user_id' => $user->id,
                    'public_id' => (string) Str::uuid(),
                    'program_id' => $program->id,
                    'student_id' => $studentId,

                    'first_name' => $this->data['first_name'],
                    'last_name' => $this->data['last_name'],
                    'national_id' => $this->data['nrc_number'],
                    'gender' => $this->data['gender'],
                    'dob' => $this->data['date_of_birth'],
                    'address' => $this->data['address'],
                    'phone' => $this->data['phone_number'],
                    'current_semester_sequence' => $this->data['semester'] ?? 1,
                    'enrollment_date' => $this->data['enrollment_date'],
                    'status' => 'active',
                ]
            );
        });

        // 8. Send Email
        if ($user && $studentId) {
            $notifier->sendWelcomeEmail(
                $user,
                $studentId,
                'student'
            );
        }
    }
}
