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
        if ($this->batch()?->cancelled()) return;

        $exists = Lecturer::where('email', $this->data['email'])
            ->orWhere('national_id', $this->data['nrc_number'])
            ->exists();

        if ($exists) {
            return;
        }

        $user = null;
        $lecturerId = null;

        // 1. Transaction Starts
        DB::transaction(function () use (&$user, &$lecturerId) {

            // 2. THE FIX: Lock the Department row.
            // This forces other workers to wait before generating an ID for this department.
            $department = Department::where('code', $this->data['department_code'])
                ->lockForUpdate()
                ->first();

            if (!$department) {
                throw new \Exception("Department {$this->data['department_code']} not found");
            }

            // 3. ID Generation (Safe because we hold the lock)
            $prefix = "LEC";
            $deptCode = $department->code ?? 'GEN';

            $sequence = Lecturer::where('department_id', $department->id)->count() + 1;
            $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);

            while(Lecturer::where('lecturer_id', $lecturerId)->exists()) {
                $sequence++;
                $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);
            }

            // 4. Create User
            $user = User::firstOrCreate(
                ['email' => $this->data['email']],
                [
                    'name' => $this->data['first_name'] . ' ' . $this->data['last_name'],
                    'password' => Hash::make($lecturerId),
                    'role' => 'LECTURER',
                ]
            );

            // 5. Create Lecturer
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

        // 6. Send Email
        if ($user && $lecturerId) {
            $notifier->sendWelcomeEmail(
                $user,
                $lecturerId,
                'lecturer'
            );
        }
    }
}
