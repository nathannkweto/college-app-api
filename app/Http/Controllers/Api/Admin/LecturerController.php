<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RegisterLecturer;
use App\Models\Lecturer;
use App\Models\Department;
use App\Services\NotificationService; // Import the Service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class LecturerController extends Controller
{
    protected $notifier;

    // Inject the NotificationService
    public function __construct(NotificationService $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * List Lecturers.
     */
    public function index(Request $request)
    {
        $query = Lecturer::with('department');

        if ($request->has('department_public_id')) {
            $dept = Department::where('public_id', $request->department_public_id)->first();
            if ($dept) $query->where('department_id', $dept->id);
        }

        // Explicitly map the data to match your OpenAPI Spec keys
        $paginated = $query->paginate(15);

        return response()->json([
            'data' => $paginated->getCollection()->map(function ($lecturer) {
                return [
                    'public_id' => $lecturer->public_id,
                    'lecturer_id' => $lecturer->lecturer_id,
                    'first_name' => $lecturer->first_name,
                    'last_name' => $lecturer->last_name,
                    'email' => $lecturer->email,
                    'title' => $lecturer->title,  // Must match Mr, Ms, etc.
                    'gender' => $lecturer->gender, // Must match M, F
                    'department' => [
                        'id' => $lecturer->department->id,
                        'name' => $lecturer->department->name,
                        'code' => $lecturer->department->code,
                    ],
                    'national_id' => $lecturer->national_id,
                    'dob' => $lecturer->dob ? $lecturer->dob->format('Y-m-d') : null,
                    'address' => $lecturer->address,
                    'phone' => $lecturer->phone,
                ];
            }),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }

    /**
     * Create a new Lecturer and User account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'department_code' => 'required|exists:departments,code',
            'gender' => 'required|in:M,F',
            'title' => 'required|string',
            'nrc_number' => 'required|string|unique:lecturers,national_id',
            'date_of_birth' => 'required|date',
            'address' => 'required|string',
            'phone_number' => 'required|string',
        ]);


        RegisterLecturer::dispatchSync($validated);

        return response()->json(['message' => 'Lecturer registration queued.'], 202);
    }

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
                yield new RegisterLecturer([
                    'last_name' => $row[0],
                    'first_name' => $row[1],
                    'nrc_number' => $row[2],
                    'gender' => $row[3],
                    'title' => $row[4],
                    'date_of_birth' => $row[5],
                    'address' => $row[6],
                    'email' => $row[7],
                    'phone_number' => $row[8],
                    'department_code' => $row[9],
                ]);
            }
            fclose($handle);
        });

        // 3. Create the Batch
        // Note: We convert the LazyCollection to an array for the batch() method.
        $batch = Bus::batch($jobs->toArray())
            ->name('Lecturer CSV Import')
            ->allowFailures()
            ->dispatch();

        return response()->json([
            'message' => 'Import started.',
            'batch_id' => $batch->id
        ], 202);

    }
}
