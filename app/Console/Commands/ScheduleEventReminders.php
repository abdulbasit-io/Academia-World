<?php

namespace App\Console\Commands;

use App\Jobs\SendEventReminder;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleEventReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:schedule-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedule reminder emails for upcoming events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scheduling event reminders...');

        $scheduled = 0;

        // Get events starting in 24 hours (±15 minutes window)
        $events24h = Event::with('registrations')
            ->where('status', 'published')
            ->whereBetween('start_date', [
                now()->addHours(24)->subMinutes(15),
                now()->addHours(24)->addMinutes(15)
            ])
            ->get();

        foreach ($events24h as $event) {
            $registeredUsers = $event->registrations()
                ->wherePivot('status', 'registered')
                ->get();

            foreach ($registeredUsers as $user) {
                SendEventReminder::dispatch($event, $user, '24h')
                    ->delay(now()->addMinutes(rand(1, 10))); // Stagger emails
                $scheduled++;
            }

            $this->line("Scheduled 24h reminders for event: {$event->title} ({$registeredUsers->count()} users)");
        }

        // Get events starting in 1 hour (±5 minutes window)
        $events1h = Event::with('registrations')
            ->where('status', 'published')
            ->whereBetween('start_date', [
                now()->addHour()->subMinutes(5),
                now()->addHour()->addMinutes(5)
            ])
            ->get();

        foreach ($events1h as $event) {
            $registeredUsers = $event->registrations()
                ->wherePivot('status', 'registered')
                ->get();

            foreach ($registeredUsers as $user) {
                SendEventReminder::dispatch($event, $user, '1h')
                    ->delay(now()->addMinutes(rand(1, 3))); // Shorter stagger for urgent reminders
                $scheduled++;
            }

            $this->line("Scheduled 1h reminders for event: {$event->title} ({$registeredUsers->count()} users)");
        }

        $this->info("Scheduled {$scheduled} reminder emails.");

        Log::info('Event reminders scheduled', [
            'scheduled_count' => $scheduled,
            'events_24h' => $events24h->count(),
            'events_1h' => $events1h->count()
        ]);

        return Command::SUCCESS;
    }
}
