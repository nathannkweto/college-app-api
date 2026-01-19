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
        // 1. Safety Check
        if ($this->batch()?->cancelled()) return;

        $exists = Lecturer::where('email', $this->data['email'])
            ->orWhere('national_id', $this->data['nrc_number'])
            ->exists();

        if ($exists) {
            return;
        }

        $department = Department::where('code', $this->data['department_code'])->first();
        if (!$department) {
            $this->fail(new \Exception("Department {$this->data['department_code']} not found"));
            return;
        }

        $user = null;
        $lecturerId = null;

        // 2. FIX: The Lock is now the OUTER wrapper.
        // This prevents the "Transaction Aborted" error when using the Database Cache Driver.
        // It also ensures better data integrity (Race Conditions are blocked before the Transaction starts).
        Cache::lock('lecturer_id_gen_' . $department->id, 10)->block(10, function () use ($department, &$user, &$lecturerId) {

            // 3. FIX: The Transaction is now the INNER wrapper.
            DB::transaction(function () use ($department, &$user, &$lecturerId) {

                $prefix = "LEC";
                $deptCode = $department->code ?? 'GEN';

                // Safely count inside the lock
                $sequence = Lecturer::where('department_id', $department->id)->count() + 1;
                $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);

                // Double check for duplicates
                // note: Changed 'student_id' to 'lecturer_id' to match your table schema below
                while(Lecturer::where('lecturer_id', $lecturerId)->exists()) {
                    $sequence++;
                    $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);
                }

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
                        'lecturer_id' => $lecturerId, // Using the generated ID

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
        });

        // 4. Send Email (Happens only if Lock & Transaction succeeded)
        if ($user && $lecturerId) {
            $notifier->sendWelcomeEmail(
                $user,
                $lecturerId,
                'lecturer'
            );
        }
    }
}
