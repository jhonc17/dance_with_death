<?php

namespace App\Http\Middleware;

use App\Models\VisitorToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateVisitor
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = VisitorToken::findValid($plain);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('visitor_token', $token);
        $request->attributes->set('visitor_email', $token->email);

        return $next($request);
    }
}
