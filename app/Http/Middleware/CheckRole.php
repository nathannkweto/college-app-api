<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Ensure user is logged in
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2. Check if the user's role matches the required role
        // We strictly compare the strings (e.g., "ADMIN" !== "STUDENT")
        if ($request->user()->role !== $role) {
            return response()->json(['message' => 'Forbidden. Access denied.'], 403);
        }

        return $next($request);
    }
}
