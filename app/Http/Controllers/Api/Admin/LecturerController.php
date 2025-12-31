<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Department;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LecturerController extends Controller
{
    protected $notifier;

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

        $paginated = $query->paginate(15);

        return response()->json([
            'data' => $paginated->getCollection()->map(function ($lecturer) {
                return [
                    'public_id'   => $lecturer->public_id,
                    'lecturer_id' => $lecturer->lecturer_id,
                    'first_name'  => $lecturer->first_name,
                    'last_name'   => $lecturer->last_name,
                    'email'       => $lecturer->email,
                    'title'       => $lecturer->title,
                    'gender'      => $lecturer->gender,
                    'department'  => [
                        'id'   => $lecturer->department->id,
                        'name' => $lecturer->department->name,
                        'code' => $lecturer->department->code,
                    ],
                    'national_id' => $lecturer->national_id,
                    'dob'         => $lecturer->dob ? $lecturer->dob->format('Y-m-d') : null,
                    'address'     => $lecturer->address,
                    'phone'       => $lecturer->phone,
                ];
            }),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }

    /**
     * Create a new Lecturer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'department_public_id' => 'required|exists:departments,public_id',
            'gender' => 'required|in:M,F',
            'title' => 'required|string',
            'national_id' => 'required|string|unique:lecturers,national_id',
            'dob' => 'required|date',
            'address' => 'required|string',
            'phone' => 'required|string',
        ]);

        try {
            $registrationResult = DB::transaction(function () use ($validated) {
                $dept = Department::where('public_id', $validated['department_public_id'])->firstOrFail();

                // A. Generate Lecturer ID
                $prefix = "LEC";
                $deptCode = $dept->code ?? 'GEN';
                $sequence = Lecturer::where('department_id', $dept->id)->count() + 1;
                $lecturerId = sprintf("%s-%s-%03d", $prefix, $deptCode, $sequence);

                // B. Create User Login
                $user = User::create([
                    'name' => $validated['title'] . ' ' . $validated['last_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($lecturerId),
                    'role' => 'LECTURER',
                ]);

                // C. Create Lecturer Profile
                $lecturer = Lecturer::create([
                    'user_id' => $user->id,
                    'public_id' => (string) Str::uuid(), // Explicitly generate UUID
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'department_id' => $dept->id,
                    'gender' => $validated['gender'],
                    'title' => $validated['title'],
                    'lecturer_id' => $lecturerId,
                    'national_id' => $validated['national_id'],
                    'dob' => $validated['dob'],
                    'address' => $validated['address'],
                    'phone' => $validated['phone'],
                ]);

                return [
                    'user' => $user,
                    'lecturer_id' => $lecturerId,
                ];
            });

            // D. Send Welcome Email (Safe Try-Catch)
            try {
                $this->notifier->sendWelcomeEmail(
                    $registrationResult['user'],
                    $registrationResult['lecturer_id'],
                    'lecturer'
                );
            } catch (\Exception $e) {
                Log::error("Lecturer Email failed: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Lecturer registered successfully',
                'lecturer_id' => $registrationResult['lecturer_id'],
            ], 201);

        } catch (\Exception $e) {
            // 1. Get the Real Database Error
            $realError = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();

            Log::error('Lecturer Registration CRASHED. Real Reason: ' . $realError);

            return response()->json([
                'error' => 'Registration Failed',
                'debug_message' => $realError, // Look at this in your Postman/Flutter response
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
