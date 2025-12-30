<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Get courses the student is currently studying (based on sequence).
     * Route: GET /api/v1/student/courses/current
     */
    public function current(Request $request)
    {
        $student = Auth::user()->profile;

        // 1. Get both collections
        $currentCourses = $student->currentCourses()->get();
        $carryOverCourses = $student->carryOverCourses()->get();

        // 2. Map and Merge them into one flat list
        $allCourses = $currentCourses->map(function ($course) {
            return [
                'code' => $course->code,
                'name' => $course->name,
            ];
        })->concat($carryOverCourses->map(function ($course) {
            return [
                'code' => $course->code,
                'name' => $course->name . ' (Repeat)', // Optional: tag carry-overs
            ];
        }));

        // 3. Wrap in the 'data' key required by your Spec
        return response()->json([
            'data' => $allCourses
        ]);
    }
}
