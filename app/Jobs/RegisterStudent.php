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
use Illuminate\Support\Facades\Cache;
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
        // 1. Safety Check
        if ($this->batch()?->cancelled()) return;

        $exists = Student::where('email', $this->data['email'])
            ->orWhere('national_id', $this->data['nrc_number'])
            ->exists();

        if ($exists) {
            return;
        }

        $program = Program::where('code', $this->data['program_code'])->first();
        if (!$program) {
            $this->fail(new \Exception("Program {$this->data['program_code']} not found"));
            return;
        }

        $user = null;
        $studentId = null;

        // 2. FIX: The Lock is now the OUTER wrapper.
        // This prevents the dead transaction from blocking the lock release.
        Cache::lock('student_id_gen_' . $program->id, 10)->block(10, function () use ($program, &$user, &$studentId) {

            // 3. FIX: The Transaction is now the INNER wrapper.
            DB::transaction(function () use ($program, &$user, &$studentId) {

                $academicYear = date('Y', strtotime($this->data['enrollment_date']));
                $code = $program->code ?? 'STU';

                // Now safely count inside the lock
                $sequence = Student::where('program_id', $program->id)->count() + 1;
                $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);

                // Double check for safety
                while(Student::where('student_id', $studentId)->exists()) {
                    $sequence++;
                    $studentId = sprintf("%s-%s-%03d", $academicYear, $code, $sequence);
                }

                // Create User
                $user = User::firstOrCreate(
                    ['email' => $this->data['email']],
                    [
                        'name' => $this->data['first_name'] . ' ' . $this->data['last_name'],
                        'password' => Hash::make($studentId),
                        'role' => 'STUDENT',
                    ]
                );

                // Create Student
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
        });

        // 4. Send Email (Only if lock & transaction succeeded)
        if ($user && $studentId) {
            $notifier->sendWelcomeEmail(
                $user,
                $studentId,
                'student'
            );
        }
    }
}
