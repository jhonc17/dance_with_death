<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAppointmentRequest;
use App\Mail\AppointmentMail;
use App\Models\Appointment;
use App\Support\AppointmentSchedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AppointmentController
{
    public function slots(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'required|string|max:64',
        ]);

        if ($problem = AppointmentSchedule::dayProblem($data['date'], $data['timezone'])) {
            return response()->json(['message' => $problem['message']], 422);
        }

        return [
            'slots' => AppointmentSchedule::availability($data['date'], $data['timezone']),
        ];
    }

    public function store(StoreAppointmentRequest $request)
    {
        $email = $request->attributes->get('visitor_email');
        $data = $request->validated();
        $startsAt = $request->input('starts_at');
        $current = AppointmentSchedule::currentForEmail($email);

        try {
            if ($current) {
                $current->update([
                    'name' => $data['name'],
                    'starts_at' => $startsAt,
                    'timezone' => $data['timezone'],
                ]);
                $appointment = $current;
            } else {
                $appointment = Appointment::create([
                    'name' => $data['name'],
                    'email' => $email,
                    'starts_at' => $startsAt,
                    'timezone' => $data['timezone'],
                ]);
            }
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'That slot is already taken.',
                'errors' => ['time' => ['That slot is already taken.']],
            ], 422);
        }

        try {
            Mail::to($email)->send(new AppointmentMail(
                $appointment->name,
                $appointment->starts_at,
                $appointment->timezone,
                $current ? 'changed' : 'booked',
            ));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => $current ? 'Appointment updated.' : 'Appointment booked.',
            'appointment' => AppointmentSchedule::toPublic($appointment),
        ], $current ? 200 : 201);
    }
}
