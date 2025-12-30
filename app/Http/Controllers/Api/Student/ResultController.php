<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user()->profile;

        // 1. Get results with relationships
        $rawResults = $student->examResults()
            ->with(['course', 'semester'])
            ->where('is_published', true)
            ->get();

        // 2. Group results by Semester Name to build the nested structure
        $semesters = $rawResults->groupBy(function ($item) {
            return $item->semester->name ?? 'Unknown Semester';
        })->map(function ($results, $semesterName) {
            return [
                'semester_name' => $semesterName,
                'results' => $results->map(function ($r) {
                    return [
                        'course_name' => $r->course->name, // YAML uses course_name
                        'grade'       => $r->grade,
                        'points'      => (float) ($r->points ?? 0), // YAML uses points
                    ];
                })->values()
            ];
        })->values();

        // 3. Wrap in 'data' key matching the Transcript schema
        return response()->json([
            'data' => [
                'gpa' => (float) $this->calculateGPA($rawResults),
                'semesters' => $semesters
            ]
        ]);
    }

    private function calculateGPA($results) {
        if ($results->isEmpty()) return 0.0;
        // Simple average for this example
        return round($results->avg('points'), 2);
    }
}
