<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Acepta token por query string (?api_token=...) además de header Bearer
        if ($request->filled('api_token') && !$request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer ' . $request->api_token);
        }

        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token requerido'], 401);
        }

        $pat = PersonalAccessToken::findToken($token);

        if (!$pat || !$pat->tokenable) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        auth()->setUser($pat->tokenable);

        return $next($request);
    }
}
