<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    /**
     * Get the currently active semester details.
     */
    public function active()
    {
        $semester = Semester::where('is_active', true)->first();

        if (!$semester) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'public_id'       => $semester->public_id,
                'academic_year'   => $semester->academic_year,
                'semester_number' => $semester->semester_number,
                'is_active'       => (bool) $semester->is_active,
                'start_date'      => $semester->start_date->format('Y-m-d'),
                'length_weeks'    => (int) $semester->length_weeks,
            ]
        ]);
    }

    public function index()
    {
        // FIX: Match the snake_case keys expected by the Flutter admin_api
        $semesters = Semester::orderBy('start_date', 'desc')->get()->map(function($s) {
            return [
                'public_id'       => $s->public_id,

                // Changed from 'academicYear' to 'academic_year'
                'academic_year'   => $s->academic_year,

                // Changed from "number".$n to raw integer, and key to 'semester_number'
                'semester_number' => (int) $s->semester_number,

                // Changed from 'startDate' to 'start_date' and generic string format
                'start_date'      => $s->start_date->format('Y-m-d'),

                // Changed from 'lengthWeeks' to 'length_weeks'
                'length_weeks'    => (int) $s->length_weeks,

                // Changed from 'isActive' to 'is_active'
                'is_active'       => (bool) $s->is_active,
            ];
        });

        return response()->json(['data' => $semesters]);
    }

    /**
     * Create a new semester.
     * Automatically deactivates previous semesters if 'is_active' is true.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string', // e.g. "2024/2025"
            'semester_number' => 'required|integer|in:1,2,3',
            'start_date' => 'required|date',
            'length_weeks' => 'required|integer|min:1',
            'is_active' => 'boolean'
        ]);

        // If setting this as active, deactivate all others first
        if ($request->is_active) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        $semester = Semester::create($validated);

        return response()->json([
            'message' => 'Semester created successfully',
            'data' => $semester
        ], 201);
    }

    /**
     * End a semester (Mark as inactive).
     */
    public function end($public_id)
    {
        $semester = Semester::where('public_id', $public_id)->firstOrFail();

        $semester->update(['is_active' => false]);

        return response()->json(['message' => 'Semester ended successfully.']);
    }
}
