<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Qualification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QualificationController extends Controller
{
    /**
     * GET /api/v1/admin/qualifications
     */
    public function index()
    {
        $qualifications = Qualification::orderBy('name')->get();

        return response()->json([
            'data' => $qualifications->map(fn (Qualification $q) => $this->format($q)),
        ]);
    }

    /**
     * POST /api/v1/admin/qualifications
     * Spec requires: name, code
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:qualifications,name',
            'code' => 'required|string|max:20|unique:qualifications,code',
        ]);

        $qualification = Qualification::create([
            'public_id' => (string) Str::uuid(),
            'name'      => $validated['name'],
            'code'      => strtoupper($validated['code']),
        ]);

        return response()->json([
            'message' => 'Qualification created successfully',
            'data'    => $this->format($qualification),
        ], 201);
    }

    /**
     * Matches Qualification schema in admin.yaml
     */
    private function format(Qualification $qualification): array
    {
        return [
            'public_id' => $qualification->public_id,
            'name'      => $qualification->name,
            'code'      => $qualification->code,
        ];
    }
}
