<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RegisterStudent;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{

    /**
     * List Students with pagination and search.
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
     * Create a new Student and their Login Account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'program_code' => 'required|exists:programs,code',
            'gender' => 'required|in:M,F',
            'nrc_number' => 'required|string|unique:students,national_id',
            'enrollment_date' => 'required|date',
            'date_of_birth' => 'required|date',
            'address' => 'required|string',
            'phone_number' => 'required|string',
        ]);

        // Dispatch a single job (or run synchronously if preferred)
        RegisterStudent::dispatchSync($validated);

        return response()->json(['message' => 'Student registration queued.'], 202);
    }

    /**
     * Batch create students from a CSV FILE.
     */
    public function batchUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        // 1. Store the file temporarily
        $relativePath = $request->file('file')->store('temp');

        // 2. Read the file efficiently using LazyCollection (Low Memory usage)
        // We defer the heavy lifting to the queue dispatch
        $jobs = LazyCollection::make(function () use ($relativePath) {
            $handle = fopen(Storage::path($relativePath), 'r');
            // Skip Header Row?
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                // DATA CLEANUP: Trim whitespace from all fields
                $row = array_map('trim', $row);

                // SKIP empty rows
                if (count($row) < 10) continue;

                // Map CSV row to Job Data
                yield new RegisterStudent([
                    'last_name' => $row[0],
                    'first_name' => $row[1],
                    'nrc_number' => $row[2],
                    'gender' => $row[3],
                    'date_of_birth' => $row[4],
                    'address' => $row[5],
                    'email' => $row[6],
                    'phone_number' => $row[7],
                    'program_code' => $row[8],
                    'semester' => $row[9],
                    'enrollment_date' => $row[10],
                ]);
            }
            fclose($handle);
        });

        // 3. Create the Batch
        // Note: We convert the LazyCollection to an array for the batch() method.
        $batch = Bus::batch($jobs->toArray())
            ->name('Student CSV Import')
            ->allowFailures()
            ->dispatch();

        return response()->json([
            'message' => 'Import started.',
            'batch_id' => $batch->id
        ], 202);

    }

}
