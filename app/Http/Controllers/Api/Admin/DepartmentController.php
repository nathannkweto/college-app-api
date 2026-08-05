<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    /**
     * GET /api/v1/admin/departments
     */
    public function index()
    {
        $departments = Department::orderBy('name')->get();

        return response()->json([
            'data' => $departments->map(fn (Department $d) => $this->format($d)),
        ]);
    }

    /**
     * POST /api/v1/admin/departments
     * Spec only requires: name, code
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'code' => 'required|string|max:20|unique:departments,code',
        ]);

        $department = Department::create([
            'public_id' => (string) Str::uuid(),
            'name'      => $validated['name'],
            'code'      => strtoupper($validated['code']),
            // department_number is internal — auto-assign if the column is still required
            'department_number' => (Department::max('department_number') ?? 0) + 1,
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'data'    => $this->format($department),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/departments/{public_id}
     */
    public function update(Request $request, string $public_id)
    {
        $department = Department::where('public_id', $public_id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:departments,name,' . $department->db_id . ',db_id',
            'code' => 'sometimes|required|string|max:20|unique:departments,code,' . $department->db_id . ',db_id',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully',
            'data'    => $this->format($department->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/admin/departments/{public_id}
     */
    public function destroy(string $public_id)
    {
        $department = Department::where('public_id', $public_id)->firstOrFail();

        // Guard: block deletion if related records exist
        if (
            $department->programs()->exists() ||
            $department->courses()->exists() ||
            $department->lecturers()->exists()
        ) {
            return response()->json([
                'message' => 'Cannot delete this department because it has related programs, courses, or lecturers.',
            ], 409);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }

    /**
     * Matches Department schema in admin.yaml exactly.
     */
    private function format(Department $department): array
    {
        return [
            'public_id' => $department->public_id,
            'name'      => $department->name,
            'code'      => $department->code,
        ];
    }
}
