<?php

namespace App\Http\Middleware;

use App\Models\AdminToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = AdminToken::findValid($plain);

        if (! $token?->user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}
