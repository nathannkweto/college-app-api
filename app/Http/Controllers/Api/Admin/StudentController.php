<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * GET /api/v1/admin/students
     */
    public function index(Request $request)
    {
        $query = Student::with(['program.qualification', 'program.department', 'level']);

        if ($request->filled('program_public_id')) {
            $program = Program::where('public_id', $request->program_public_id)->first();
            if ($program) {
                $query->where('program_db_id', $program->db_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('national_id_number', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('last_name')->get();

        return response()->json([
            'data' => $students->map(fn (Student $s) => $this->format($s)),
        ]);
    }

    /**
     * POST /api/v1/admin/students
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'nrc_number'      => 'required|string|max:255',
            'gender'          => 'required|in:M,F',
            'date_of_birth'   => 'required|date',
            'address'         => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email|unique:students,email',
            'phone_number'    => 'required|string|max:255',
            'program_code'    => 'required|string|exists:programs,code',
            'enrollment_date' => 'required|date',
        ]);

        $program = Program::where('code', strtoupper($validated['program_code']))->firstOrFail();

        // Generate a readable student ID, e.g. 2026-DN-001
        $year = date('Y', strtotime($validated['enrollment_date']));
        $prefix = $year . '-' . ($program->code ?? 'STU') . '-';
        $last = Student::where('id', 'like', $prefix . '%')->orderByDesc('id')->value('id');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;
        $studentId = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

        // Create login user
        $user = User::create([
            'public_id' => (string) Str::uuid(),
            'name'      => $validated['first_name'] . ' ' . $validated['last_name'],
            'email'     => $validated['email'],
            'password'  => Hash::make('password123'), // default password
            'role'      => 'student',
        ]);

        $student = Student::create([
            'public_id'          => (string) Str::uuid(),
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'],
            'national_id_number' => $validated['nrc_number'],
            'gender'             => $validated['gender'],
            'dob'                => $validated['date_of_birth'],
            'address'            => $validated['address'],
            'email'              => $validated['email'],
            'phone'              => $validated['phone_number'],
            'id'                 => $studentId,
            'program_db_id'      => $program->db_id,
            'level_db_id'        => $program->level_db_id, // keep if still required
            'enrollment_date'    => $validated['enrollment_date'],
            'user_db_id'         => $user->db_id,
            // optional columns if they exist:
            // 'current_semester_sequence' => 1,
            // 'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Student created successfully',
            'data'    => $this->format($student->load(['program.qualification', 'program.department'])),
        ], 201);
    }

    /**
     * GET /api/v1/admin/students/{public_id}
     */
    public function show(string $public_id)
    {
        $student = Student::with(['program.qualification', 'program.department', 'level'])
            ->where('public_id', $public_id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->format($student),
        ]);
    }

    /**
     * PUT /api/v1/admin/students/{public_id}
     */
    public function update(Request $request, string $public_id)
    {
        $student = Student::where('public_id', $public_id)->firstOrFail();

        $validated = $request->validate([
            'first_name'      => 'sometimes|string|max:255',
            'last_name'       => 'sometimes|string|max:255',
            'email'           => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($student->user_db_id, 'db_id'),
                Rule::unique('students', 'email')->ignore($student->db_id, 'db_id'),
            ],
            'program_code'    => 'sometimes|string|exists:programs,code',
            'gender'          => 'sometimes|in:M,F',
            'enrollment_date' => 'sometimes|date',
            'nrc_number'      => 'sometimes|string|max:255',
            'date_of_birth'   => 'sometimes|date',
            'address'         => 'sometimes|string|max:255',
            'phone_number'    => 'sometimes|string|max:255',
            'status'          => 'sometimes|in:active,inactive,graduated,suspended',
        ]);

        $data = [];

        if (isset($validated['first_name'])) $data['first_name'] = $validated['first_name'];
        if (isset($validated['last_name']))  $data['last_name']  = $validated['last_name'];
        if (isset($validated['email']))      $data['email']      = $validated['email'];
        if (isset($validated['gender']))     $data['gender']     = $validated['gender'];
        if (isset($validated['enrollment_date'])) $data['enrollment_date'] = $validated['enrollment_date'];
        if (isset($validated['nrc_number'])) $data['national_id_number'] = $validated['nrc_number'];
        if (isset($validated['date_of_birth'])) $data['dob'] = $validated['date_of_birth'];
        if (isset($validated['address']))    $data['address'] = $validated['address'];
        if (isset($validated['phone_number'])) $data['phone'] = $validated['phone_number'];
        if (isset($validated['status']))     $data['status'] = $validated['status'];

        if (isset($validated['program_code'])) {
            $program = Program::where('code', strtoupper($validated['program_code']))->firstOrFail();
            $data['program_db_id'] = $program->db_id;
            if ($program->level_db_id) {
                $data['level_db_id'] = $program->level_db_id;
            }
        }

        $student->update($data);

        // Keep user name/email in sync
        if ($student->user) {
            $student->user->update([
                'name'  => ($data['first_name'] ?? $student->first_name) . ' ' . ($data['last_name'] ?? $student->last_name),
                'email' => $data['email'] ?? $student->user->email,
            ]);
        }

        return response()->json([
            'message' => 'Student updated successfully',
            'data'    => $this->format($student->fresh(['program.qualification', 'program.department'])),
        ]);
    }

    /**
     * DELETE /api/v1/admin/students/{public_id}
     */
    public function destroy(string $public_id)
    {
        $student = Student::where('public_id', $public_id)->firstOrFail();

        if ($student->results()->exists() || $student->fees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete student because they have related results or fees.',
            ], 409);
        }

        $student->delete();

        return response()->json([
            'message' => 'Student deleted successfully',
        ]);
    }

    /**
     * POST /api/v1/admin/students/batch-upload
     */
    public function batchUpload(Request $request)
    {
        return response()->json([
            'message' => 'Batch upload endpoint - implementation pending',
        ], 501);
    }

    /**
     * POST /api/v1/admin/students/promotion-preview
     */
    public function promotionPreview(Request $request)
    {
        return response()->json([
            'eligible'  => [],
            'repeating' => [],
        ]);
    }

    /**
     * POST /api/v1/admin/students/promote
     */
    public function promote(Request $request)
    {
        return response()->json([
            'message' => 'Promotion job started (implementation pending)',
        ]);
    }

    /**
     * Matches Student schema in admin.yaml
     */
    private function format(Student $student): array
    {
        return [
            'public_id'                 => $student->public_id,
            'student_id'                => $student->id,
            'first_name'                => $student->first_name,
            'last_name'                 => $student->last_name,
            'email'                     => $student->email,
            'enrollment_date'           => $student->enrollment_date?->format('Y-m-d'),
            'national_id'               => $student->national_id_number,
            'gender'                    => $student->gender,
            'program'                   => $student->program ? [
                'public_id'       => $student->program->public_id,
                'name'            => $student->program->name,
                'code'            => $student->program->code ?? $student->program->tag,
                'total_semesters' => $student->program->total_semesters ?? 6,
                'qualification'   => $student->program->qualification ? [
                    'public_id' => $student->program->qualification->public_id,
                    'name'      => $student->program->qualification->name,
                    'code'      => $student->program->qualification->code,
                ] : null,
                'department' => $student->program->department ? [
                    'public_id' => $student->program->department->public_id,
                    'name'      => $student->program->department->name,
                    'code'      => $student->program->department->code,
                ] : null,
            ] : null,
            'current_semester_sequence' => $student->current_semester_sequence ?? 1,
            'status'                    => $student->status ?? 'active',
            'dob'                       => $student->dob?->format('Y-m-d'),
            'address'                   => $student->address,
            'phone'                     => $student->phone,
        ];
    }
}
