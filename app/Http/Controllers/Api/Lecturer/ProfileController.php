<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user();
        $lecturer = $user->profile()->with('department')->first();

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }

        return response()->json([
            'data' => [
                'first_name' => $lecturer->first_name,
                'last_name' => $lecturer->last_name,
                'title' => $lecturer->title,
                'department' => $lecturer->department->name ?? 'N/A',
                'lecturer_id' => $lecturer->lecturer_id,
                'avatar_url' => null,
            ]
        ]);
    }
}
