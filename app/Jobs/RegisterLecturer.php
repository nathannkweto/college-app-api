<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\Lecturer;
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
use Illuminate\Support\Str;
use App\Services\NotificationService;

class RegisterLecturer implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(NotificationService $notifier)
    {
        // 1. Safety Check: Use '?->' because batch() is null if run individually
        if ($this->batch()?->cancelled()) return;

        $exists = Lecturer::where('email', $this->data['email'])
            ->orWhere('national_id', $this->data['nrc_number'])
            ->exists();

        if ($exists) {
            return;
        }

        $user = null;
        $lecturerId = null;

        $department = Department::where('code', $this->data['department_code'])->first();
        if (!$department) {
            // Fails the job so you see it in "failed_jobs" table
            $this->fail(new \Exception("Department {$this->data['department_code']} not found"));
            return;
        }

        // 2. Pass variables into the closure.
        DB::transaction(function () use ($department, &$user, &$lecturerId) {

            // 3. CRITICAL: Atomic Lock prevents duplicate IDs during bulk import
            // This forces workers to queue up for 5 seconds to generate IDs one by one
            Cache::lock('lecturer_id_gen_' . $department->id, 5)->block(5, function () use ($department, &$lecturerId) {

                $prefix = "LEC";
                $deptCode = $department->code ?? 'GEN';

                // Now safely count
                $sequence = Lecturer::where('department_id', $department->id)->count() + 1;
                $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);

                // Double check for safety
                while(Lecturer::where('student_id', $lecturerId)->exists()) {
                    $sequence++;
                    $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);
                }
            });

            // Create User
            $user = User::firstOrCreate(
                ['email' => $this->data['email']],
                [
                    'name' => $this->data['first_name'] . ' ' . $this->data['last_name'],
                    'password' => Hash::make($lecturerId),
                    'role' => 'LECTURER',
                ]
            );

            // Create Lecturer
            Lecturer::create(
                [
                    'email' => $this->data['email'],
                    'user_id' => $user->id,
                    'public_id' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'lecturer_id' => $lecturerId,

                    'first_name' => $this->data['first_name'],
                    'last_name' => $this->data['last_name'],
                    'national_id' => $this->data['nrc_number'],
                    'gender' => $this->data['gender'],
                    'dob' => $this->data['date_of_birth'],
                    'address' => $this->data['address'],
                    'phone' => $this->data['phone_number'],
                    'title' => $this->data['title'],
                ]
            );
        });

        // 4. Send Email
        if ($user && $lecturerId) {
            $notifier->sendWelcomeEmail(
                $user,
                $lecturerId,
                'lecturer'
            );
        }
    }
}
