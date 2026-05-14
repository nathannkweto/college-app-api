<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Jobs\EnrollStudentForSemester;
use Illuminate\Support\Facades\Bus;

class RecoverEnrollments extends Command
{
    // This is what you'll type in the terminal to run it
    protected $signature = 'app:recover-enrollments {semester}';
    protected $description = 'Manually triggers enrollment jobs for all active students';

    public function handle()
    {
        $semesterId = $this->argument('semester');
        
        $this->info("Preparing batch for Semester ID: $semesterId...");

        $jobs = [];
        Student::where('status', 'active')->chunk(100, function ($students) use (&$jobs, $semesterId) {
            foreach ($students as $student) {
                // Assuming isStartOfYear is true for this recovery
                $jobs[] = new EnrollStudentForSemester($student->id, $semesterId, true);
            }
        });

        if (empty($jobs)) {
            $this->error("No active students found.");
            return;
        }

        $batch = Bus::batch($jobs)
            ->name('Manual Student Enrollment Recovery')
            ->dispatch();

        $this->info("Batch dispatched successfully!");
        $this->info("Batch ID: {$batch->id}");
        $this->comment("Make sure your queue worker is running: php artisan queue:work");
    }
}