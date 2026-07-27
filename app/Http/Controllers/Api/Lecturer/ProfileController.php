<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $lecturer = Auth::user()->profile()->with('department')->first();

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        return response()->json([
            'data' => [
                'public_id' => $lecturer->public_id,
                'lecturer_id' => $lecturer->id, // business ID, e.g. "LEC-IT-001"
                'first_name' => $lecturer->first_name,
                'last_name' => $lecturer->last_name,
                'title' => $lecturer->title,
                'email' => $lecturer->email,
                'phone' => $lecturer->phone,
                'department' => $lecturer->department?->name,
                'employment_date' => $lecturer->employment_date?->format('Y-m-d'),
            ],
        ]);
    }
}
