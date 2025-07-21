<?php

namespace App\Jobs;

use App\Mail\EventReminder;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEventReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Event $event,
        public User $user,
        public string $reminderType
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verify the user is still registered for the event
            $isRegistered = $this->event->registrations()
                ->wherePivot('user_id', $this->user->id)
                ->wherePivot('status', 'registered')
                ->exists();

            if (!$isRegistered) {
                Log::info('User no longer registered for event, skipping reminder', [
                    'user_id' => $this->user->id,
                    'event_id' => $this->event->id,
                    'reminder_type' => $this->reminderType
                ]);
                return;
            }

            // Verify the event is still active
            if (!$this->event->isActive()) {
                Log::info('Event is no longer active, skipping reminder', [
                    'event_id' => $this->event->id,
                    'reminder_type' => $this->reminderType
                ]);
                return;
            }

            Mail::to($this->user->email)->send(
                new EventReminder($this->user, $this->event, $this->reminderType)
            );

            Log::info('Event reminder sent successfully', [
                'user_id' => $this->user->id,
                'event_id' => $this->event->id,
                'reminder_type' => $this->reminderType,
                'recipient_email' => $this->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send event reminder', [
                'user_id' => $this->user->id,
                'event_id' => $this->event->id,
                'reminder_type' => $this->reminderType,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Event reminder job failed', [
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'reminder_type' => $this->reminderType,
            'exception' => $exception->getMessage()
        ]);
    }
}
