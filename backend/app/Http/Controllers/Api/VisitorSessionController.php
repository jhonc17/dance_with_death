<?php

namespace App\Http\Controllers\Api;

use App\Mail\LoginCodeMail;
use App\Models\VisitorLoginCode;
use App\Models\VisitorToken;
use App\Support\AppointmentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class VisitorSessionController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($data['email']));
        $code = (string) random_int(100000, 999999);

        VisitorLoginCode::issue($email, $code);

        Mail::to($email)->send(new LoginCodeMail($code));

        return response()->json([
            'message' => 'We sent a confirmation code to your email.',
        ], 201);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
        ]);

        $email = strtolower(trim($data['email']));
        $status = VisitorLoginCode::consume($email, $data['code']);

        if ($status === 'expired') {
            return response()->json(['message' => 'Code expired, request a new one.'], 422);
        }

        if ($status === 'invalid') {
            return response()->json([
                'message' => 'Invalid code.',
                'errors' => ['code' => ['The confirmation code is not valid.']],
            ], 422);
        }

        return [
            'token' => VisitorToken::issue($email),
            'email' => $email,
            'appointment' => AppointmentSchedule::toPublic(
                AppointmentSchedule::currentForEmail($email)
            ),
        ];
    }

    public function discard(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        VisitorLoginCode::discard(strtolower(trim($data['email'])));

        return response()->noContent();
    }

    public function me(Request $request)
    {
        $email = $request->attributes->get('visitor_email');

        return [
            'email' => $email,
            'appointment' => AppointmentSchedule::toPublic(
                AppointmentSchedule::currentForEmail($email)
            ),
        ];
    }

    public function logout(Request $request)
    {
        $request->attributes->get('visitor_token')?->delete();

        return ['message' => 'Signed out.'];
    }
}
