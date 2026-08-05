<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RegisterLecturer;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LecturerController extends Controller
{
    /**
     * GET /api/v1/admin/lecturers
     */
    public function index(Request $request)
    {
        $query = Lecturer::with('department');

        if ($request->filled('department_public_id')) {
            $department = Department::where('public_id', $request->department_public_id)->first();
            if ($department) {
                $query->where('department_db_id', $department->db_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $lecturers = $query->orderBy('last_name')->get();

        return response()->json([
            'data' => $lecturers->map(fn (Lecturer $l) => $this->format($l)),
        ]);
    }

    /**
     * POST /api/v1/admin/lecturers
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|unique:lecturers,email',
            'department_code' => 'required|string|exists:departments,code',
            'title' => 'required|in:Mr,Ms,Mrs,Dr,Prof',
            'gender' => 'required|in:M,F',
            'nrc_number' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
        ]);

        $department = Department::where('code', $validated['department_code'])->firstOrFail();

        $lecturerId = $this->generateLecturerId($department);

        $user = User::create([
            'public_id' => (string) Str::uuid(),
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make('password123'),
            'role' => 'lecturer',
        ]);

        $lecturer = Lecturer::create([
            'public_id' => (string) Str::uuid(),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'department_db_id' => $department->db_id,
            'title' => $validated['title'],
            'gender' => $validated['gender'],
            'national_id_number' => $validated['nrc_number'] ?? null,
            'dob' => $validated['date_of_birth'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone_number'] ?? '',
            'id' => $lecturerId,
            // Not in the spec's request body — defaulting to registration date
            // until there's a real "date hired" field to collect separately.
            'employment_date' => now()->toDateString(),
            'user_db_id' => $user->db_id,
        ]);

        return response()->json([
            'message' => 'Lecturer created successfully',
            'data' => $this->format($lecturer->load('department')),
        ], 201);
    }

    /**
     * GET /api/v1/admin/lecturers/{public_id}
     */
    public function show(string $public_id)
    {
        $lecturer = Lecturer::with('department')->where('public_id', $public_id)->firstOrFail();

        return response()->json([
            'data' => $this->format($lecturer),
        ]);
    }

    /**
     * PUT /api/v1/admin/lecturers/{public_id}
     */
    public function update(Request $request, string $public_id)
    {
        $lecturer = Lecturer::where('public_id', $public_id)->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes', 'required', 'email',
                Rule::unique('users', 'email')->ignore($lecturer->user_db_id, 'db_id'),
                Rule::unique('lecturers', 'email')->ignore($lecturer->db_id, 'db_id'),
            ],
            'department_code' => 'sometimes|required|string|exists:departments,code',
            'title' => 'sometimes|required|in:Mr,Ms,Mrs,Dr,Prof',
            'gender' => 'sometimes|required|in:M,F',
            'nrc_number' => 'sometimes|nullable|string|max:255',
            'date_of_birth' => 'sometimes|nullable|date',
            'address' => 'sometimes|nullable|string|max:255',
            'phone_number' => 'sometimes|nullable|string|max:255',
        ]);

        $data = [];
        if (isset($validated['first_name'])) $data['first_name'] = $validated['first_name'];
        if (isset($validated['last_name'])) $data['last_name'] = $validated['last_name'];
        if (isset($validated['email'])) $data['email'] = $validated['email'];
        if (isset($validated['title'])) $data['title'] = $validated['title'];
        if (isset($validated['gender'])) $data['gender'] = $validated['gender'];
        if (array_key_exists('nrc_number', $validated)) $data['national_id_number'] = $validated['nrc_number'];
        if (array_key_exists('date_of_birth', $validated)) $data['dob'] = $validated['date_of_birth'];
        if (array_key_exists('address', $validated)) $data['address'] = $validated['address'];
        if (array_key_exists('phone_number', $validated)) $data['phone'] = $validated['phone_number'];

        if (isset($validated['department_code'])) {
            $department = Department::where('code', $validated['department_code'])->firstOrFail();
            $data['department_db_id'] = $department->db_id;
        }

        $lecturer->update($data);

        if ($lecturer->user) {
            $lecturer->user->update([
                'name' => ($data['first_name'] ?? $lecturer->first_name) . ' ' . ($data['last_name'] ?? $lecturer->last_name),
                'email' => $data['email'] ?? $lecturer->user->email,
            ]);
        }

        return response()->json([
            'message' => 'Lecturer updated successfully',
            'data' => $this->format($lecturer->fresh('department')),
        ]);
    }

    /**
     * DELETE /api/v1/admin/lecturers/{public_id}
     */
    public function destroy(string $public_id)
    {
        $lecturer = Lecturer::where('public_id', $public_id)->firstOrFail();

        if ($lecturer->timetableEntries()->exists()) {
            return response()->json([
                'message' => 'Cannot delete lecturer because they still have timetable entries assigned.',
            ], 409);
        }

        $lecturer->delete();

        return response()->json([
            'message' => 'Lecturer deleted successfully',
        ]);
    }

    /**
     * POST /api/v1/admin/lecturers/batch-upload
     *
     * Expected CSV columns, in order (header row skipped):
     * last_name, first_name, title, gender, department_code, email,
     * phone_number, nrc_number, date_of_birth, address
     */

    // public function batchUpload(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file|mimes:csv,txt',
    //     ]);

    //     $relativePath = $request->file('file')->store('temp');

    //     $jobs = LazyCollection::make(function () use ($relativePath) {
    //         $handle = fopen(Storage::path($relativePath), 'r');
    //         fgetcsv($handle); // skip header row

    //         while (($row = fgetcsv($handle)) !== false) {
    //             $row = array_map('trim', $row);

    //             if (count($row) < 6) {
    //                 continue;
    //             }

    //             yield new RegisterLecturer([
    //                 'last_name' => $row[0],
    //                 'first_name' => $row[1],
    //                 'title' => $row[2],
    //                 'gender' => $row[3],
    //                 'department_code' => $row[4],
    //                 'email' => $row[5],
    //                 'phone_number' => $row[6] ?? null,
    //                 'nrc_number' => $row[7] ?? null,
    //                 'date_of_birth' => $row[8] ?? null,
    //                 'address' => $row[9] ?? null,
    //             ]);
    //         }
    //         fclose($handle);
    //     });

    //     $batch = Bus::batch($jobs->toArray())
    //         ->name('Lecturer CSV Import')
    //         ->allowFailures()
    //         ->dispatch();

    //     return response()->json([
    //         'message' => 'Import started.',
    //         'batch_id' => $batch->id,
    //     ], 202);
    // }

    public function batchUpload(Request $request)
    {
        return response()->json([
            'message' => 'Batch upload started (implementation pending)',
        ]);
    }

    private function generateLecturerId(Department $department): string
    {
        $prefix = $department->code . '-';
        $last = Lecturer::where('id', 'like', $prefix . '%')->orderByDesc('id')->value('id');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Matches Lecturer schema in admin.yaml
     */
    private function format(Lecturer $lecturer): array
    {
        return [
            'public_id' => $lecturer->public_id,
            'lecturer_id' => $lecturer->id,
            'first_name' => $lecturer->first_name,
            'last_name' => $lecturer->last_name,
            'email' => $lecturer->email,
            'title' => $lecturer->title,
            'gender' => $lecturer->gender,
            'department' => $lecturer->department ? [
                'public_id' => $lecturer->department->public_id,
                'name' => $lecturer->department->name,
                'code' => $lecturer->department->code,
            ] : null,
            'nrc_number' => $lecturer->national_id_number,
            'date_of_birth' => $lecturer->dob?->format('Y-m-d'),
            'address' => $lecturer->address,
            'phone_number' => $lecturer->phone,
        ];
    }
}
