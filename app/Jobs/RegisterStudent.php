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

        // 1. Check existence first (Read-only, safe outside transaction)
        $exists = Student::where('email', $this->data['email'])
            ->orWhere('national_id', $this->data['nrc_number'])
            ->exists();

        if ($exists) {
            return;
        }

        $user = null;
        $studentId = null;

        // 2. Start the Transaction immediately. No Cache::lock.
        DB::transaction(function () use (&$user, &$studentId) {

            // 3. THE FIX: Fetch the Program INSIDE the transaction using lockForUpdate().
            // This creates a database-level lock on this specific Program row.
            // Other workers for this same program will pause here until this transaction commits.
            $program = Program::where('code', $this->data['program_code'])
                ->lockForUpdate()
                ->first();

            if (!$program) {
                // Throwing inside a transaction auto-rollbacks
                throw new \Exception("Program {$this->data['program_code']} not found");
            }

            // 4. ID Generation Logic
            // Because we hold the lock on $program, we are guaranteed to be the only one counting right now.
            $academicYear = date('Y', strtotime($this->data['enrollment_date']));
            $code = $program->code ?? 'STU';

            $sequence = Student::where('program_id', $program->id)->count() + 1;
            $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);

            // Double check (Fail-safe)
            while(Student::where('student_id', $studentId)->exists()) {
                $sequence++;
                $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);
            }

            // 5. Create User
            $user = User::firstOrCreate(
                ['email' => $this->data['email']],
                [
                    'name' => $this->data['first_name'] . ' ' . $this->data['last_name'],
                    'password' => Hash::make($studentId),
                    'role' => 'STUDENT',
                ]
            );

            // 6. Create Student
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

        // 7. Send Email
        if ($user && $studentId) {
            $notifier->sendWelcomeEmail(
                $user,
                $studentId,
                'student'
            );
        }
    }
}
