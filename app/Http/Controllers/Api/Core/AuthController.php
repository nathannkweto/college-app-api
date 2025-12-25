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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Find User
        $user = User::where('email', $request->email)->first();

        // 3. Check Credentials
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // 4. Create Token
        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

        // 5. Get Profile ID (fallback to User Public ID if no profile linked yet)
        $profileId = $user->profileable ? $user->profileable->public_id : $user->public_id;

        // 6. Return JSON matching core-api.yaml
        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'profile_public_id' => $profileId,
        ], 200);
    }
}
