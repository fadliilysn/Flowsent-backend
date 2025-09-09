<?php

namespace App\Http\Middleware;

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // parse token & ambil payload
            $payload = JWTAuth::parseToken()->getPayload();

            // bisa ambil email dari token
            $request->merge([
                'auth_email' => $payload->get('email'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
