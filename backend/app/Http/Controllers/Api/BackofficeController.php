<?php

namespace App\Http\Controllers\Api;

use App\Mail\AppointmentMail;
use App\Models\Appointment;
use App\Support\AppointmentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BackofficeController
{
    public function slots(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'required|string|max:64',
        ]);

        if ($problem = AppointmentSchedule::dayProblem($data['date'], $data['timezone'], rejectPast: false)) {
            return response()->json(['message' => $problem['message']], 422);
        }

        return [
            'slots' => AppointmentSchedule::availability($data['date'], $data['timezone'], withBooking: true),
        ];
    }

    public function cancelAppointment(Appointment $appointment)
    {
        $mail = new AppointmentMail(
            $appointment->name,
            $appointment->starts_at,
            $appointment->timezone,
            'cancelled',
            ['bookingUrl' => rtrim((string) config('app.frontend_url'), '/')],
        );
        $email = $appointment->email;

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not email the client. The appointment was not cancelled.',
            ], 503);
        }

        $appointment->delete();

        return [
            'message' => 'Appointment cancelled. The client was notified.',
        ];
    }
}
