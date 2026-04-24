<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validate
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Find User
        $user = User::where('email', $validated['email'])->first();

        // 3. Check Credentials
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // 4. Create Token
        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

        // 5. Resolve Profile Public ID
        $profileId = optional($user->profile)->public_id ?? $user->public_id;

        // 6. Return SPEC-COMPLIANT response
        return response()->json([
            'token' => $token,
            'user' => [
                'profile_public_id' => $profileId,
                'email' => $user->email,
                'role' => strtoupper($user->role), // Ensure enum consistency
            ],
        ], 200);
    }

    /**
     * Logout User (Revoke Token).
     */
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.'
        ], 200);
    }
}
