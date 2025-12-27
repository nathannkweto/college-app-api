<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Department;
use App\Models\Qualification;
use App\Models\Course;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index()
    {
        return Program::with(['qualification', 'department'])->paginate(15);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:programs,name',
            'code' => 'required|string|unique:programs,code|max:10',
            'qualification_public_id' => 'required|exists:qualifications,public_id',
            'department_public_id' => 'required|exists:departments,public_id',
            'total_semesters' => 'required|integer|min:1|max:12', // This defines "Levels"
        ]);

        $qualId = Qualification::getIdFromPublicId($validated['qualification_public_id']);
        $deptId = Department::getIdFromPublicId($validated['department_public_id']);

        $program = Program::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'qualification_id' => $qualId,
            'department_id' => $deptId,
            'total_semesters' => $validated['total_semesters'],
        ]);

        return response()->json($program, 201);
    }

    public function addCourse(Request $request, $publicId)
    {
        $validated = $request->validate([
            'course_public_id' => 'required|exists:courses,public_id',
            'semester_sequence' => 'required|integer|min:1'
        ]);

        $program = Program::where('public_id', $publicId)->firstOrFail();
        $courseId = Course::getIdFromPublicId($validated['course_public_id']);

        // Attach course to program for a specific semester level
        $program->courses()->attach($courseId, [
            'semester_sequence' => $validated['semester_sequence']
        ]);

        return response()->json(['message' => 'Course added to curriculum']);
    }
}
