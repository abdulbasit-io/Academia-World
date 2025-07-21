<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminEventNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Event $event,
        public string $notificationType, // 'new_event', 'new_registration', 'event_cancelled'
        public ?User $user = null // for registration notifications
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjectMap = [
            'new_event' => 'New Event Published',
            'new_registration' => 'New Event Registration',
            'event_cancelled' => 'Event Cancelled',
        ];
        
        return new Envelope(
            subject: '[Admin] ' . ($subjectMap[$this->notificationType] ?? 'Event Notification'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.event-notification',
            with: [
                'event' => $this->event,
                'notificationType' => $this->notificationType,
                'user' => $this->user,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
