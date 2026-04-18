<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $memberName,
        public readonly string $email,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Your Password — eWards PMS');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
