<?php

namespace App\Http\Controllers\Api;

use App\Models\AdminToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return [
            'token' => AdminToken::issue($user),
            'user' => $this->userPayload($user),
        ];
    }

    public function logout(Request $request)
    {
        $request->attributes->get('admin_token')?->delete();

        return ['message' => 'Signed out.'];
    }

    public function me(Request $request)
    {
        return ['user' => $this->userPayload($request->user())];
    }

    private function userPayload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
