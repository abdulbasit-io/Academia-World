<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $userData;
    public string $verificationUrl;

    public function __construct(array $userData, string $verificationUrl)
    {
        $this->userData = $userData;
        $this->verificationUrl = $verificationUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - Academia World',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.verification',
            with: [
                'user' => (object) $this->userData,
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
