<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AppointmentMail extends Mailable
{
    public function __construct(
        private string $clientName,
        private CarbonInterface $startsAt,
        private string $timezone,
        private string $action,
        private array $extra = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Appointment {$this->action} - Dance with Death",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: "emails.appointment-{$this->action}",
            with: $this->details(),
        );
    }

    private function details(): array
    {
        $local = $this->startsAt->copy()->timezone($this->timezone);

        return [
            'name' => $this->clientName,
            'date' => $local->toDateString(),
            'time' => $local->format('H:i'),
            'timezone' => $this->timezone,
            ...$this->extra,
        ];
    }
}
