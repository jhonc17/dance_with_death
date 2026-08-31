<?php

namespace App\Support;

use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Exception;

class AppointmentSchedule
{
    public static function slotHours(): array
    {
        return [
            '09:00', '10:00', '11:00', '12:00', '13:00',
            '14:00', '15:00', '16:00', '17:00', '18:00',
        ];
    }

    public static function toUtc(string $date, string $time, string $timezone): CarbonInterface
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $timezone)->utc();
    }

    public static function dayProblem(string $date, string $timezone, bool $rejectPast = true): ?array
    {
        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            return ['field' => 'timezone', 'message' => 'Invalid timezone.'];
        }

        $localDay = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();

        if ($rejectPast && $localDay->lt(Carbon::now($timezone)->startOfDay())) {
            return ['field' => 'date', 'message' => 'Past dates are not allowed.'];
        }

        if (! $localDay->isWeekday()) {
            return ['field' => 'date', 'message' => 'Weekdays only (Mon–Fri).'];
        }

        return null;
    }

    public static function currentForEmail(string $email): ?Appointment
    {
        return Appointment::query()
            ->where('email', strtolower($email))
            ->where('starts_at', '>=', now('UTC'))
            ->orderBy('starts_at')
            ->first();
    }

    public static function toPublic(?Appointment $appointment): ?array
    {
        if (! $appointment) {
            return null;
        }

        return [
            'name' => $appointment->name,
            'starts_at' => $appointment->starts_at->toIso8601String(),
        ];
    }

    public static function availability(string $date, string $timezone, bool $withBooking = false): array
    {
        $now = now('UTC');
        $hours = [];
        foreach (self::slotHours() as $time) {
            $hours[$time] = self::toUtc($date, $time, $timezone);
        }

        $byTs = [];
        foreach (Appointment::whereIn('starts_at', array_values($hours))->get() as $row) {
            $byTs[$row->starts_at->timestamp] = $row;
        }

        $slots = [];
        foreach ($hours as $time => $at) {
            $row = $byTs[$at->timestamp] ?? null;
            $slots[] = $withBooking
                ? [
                    'time' => $time,
                    'booking' => $row ? [
                        'id' => $row->id,
                        'name' => $row->name,
                        'email' => $row->email,
                    ] : null,
                ]
                : [
                    'time' => $time,
                    'available' => $at->gte($now) && ! $row,
                ];
        }

        return $slots;
    }
}
