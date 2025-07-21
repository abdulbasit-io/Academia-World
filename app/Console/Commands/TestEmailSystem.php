<?php

namespace App\Console\Commands;

use App\Mail\EventRegistrationConfirmation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:test {--user-id=1} {--event-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the email system with sample data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user-id');
        $eventId = $this->option('event-id');

        $user = User::find($userId);
        $event = Event::with('host')->find($eventId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return Command::FAILURE;
        }

        if (!$event) {
            $this->error("Event with ID {$eventId} not found.");
            return Command::FAILURE;
        }

        try {
            $this->info("Sending test email to: {$user->email}");
            $this->info("Event: {$event->title}");

            Mail::to($user->email)->send(
                new EventRegistrationConfirmation($user, $event)
            );

            $this->info("✅ Test email sent successfully!");
            $this->line("📧 Check the log file: storage/logs/laravel.log");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
