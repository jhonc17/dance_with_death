<?php

namespace App\Http\Requests;

use App\Support\AppointmentSchedule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'timezone' => 'required|string|max:64',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = $this->input('date');
            $time = $this->input('time');
            $timezone = $this->input('timezone');

            if ($problem = AppointmentSchedule::dayProblem($date, $timezone)) {
                $validator->errors()->add($problem['field'], $problem['message']);
            }

            if (! in_array($time, AppointmentSchedule::slotHours(), true)) {
                $validator->errors()->add('time', 'Invalid time slot.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $startsAt = AppointmentSchedule::toUtc($date, $time, $timezone);

            if ($startsAt->lt(now('UTC'))) {
                $validator->errors()->add('time', 'That slot is already in the past.');
            }

            $this->merge([
                'starts_at' => $startsAt,
            ]);
        });
    }
}
