<?php

namespace App\Jobs;

use App\Mail\AdminEventNotification;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAdminNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Event $event,
        public string $notificationType, // 'new_event', 'new_registration', 'event_cancelled'
        public ?User $user = null // for registration notifications
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get all admin users
            $admins = User::where('is_admin', true)->get();

            if ($admins->isEmpty()) {
                Log::warning('No admin users found to send notification', [
                    'event_id' => $this->event->id,
                    'notification_type' => $this->notificationType
                ]);
                return;
            }

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(
                    new AdminEventNotification($this->event, $this->notificationType, $this->user)
                );
            }

            Log::info('Admin notifications sent successfully', [
                'event_id' => $this->event->id,
                'notification_type' => $this->notificationType,
                'admin_count' => $admins->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notifications', [
                'event_id' => $this->event->id,
                'notification_type' => $this->notificationType,
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
        Log::error('Admin notification job failed', [
            'event_id' => $this->event->id,
            'notification_type' => $this->notificationType,
            'exception' => $exception->getMessage()
        ]);
    }
}
