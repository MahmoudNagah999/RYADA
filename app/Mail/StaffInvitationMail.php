<?php

namespace App\Mail;

use App\Domain\Auth\Enums\RoleName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class StaffInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $acceptUrl,
        public readonly string $clubName,
        public readonly RoleName $role,
        public readonly Carbon $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.staff_invitation.subject', ['club' => $this->clubName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.staff-invitation',
            with: [
                'clubName' => $this->clubName,
                'roleLabel' => __('mail.roles.'.$this->role->value),
                'expiresAt' => $this->expiresAt->toDateString(),
            ],
        );
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }
}
