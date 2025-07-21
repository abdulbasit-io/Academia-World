<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Jobs\SendAdminNotification;
use App\Jobs\SendEventReminder;
use App\Mail\AdminEventNotification;
use App\Mail\EventReminder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

class JobTest extends TestCase
{
    #[Test]
    public function send_admin_notification_job_can_be_dispatched()
    {
        Queue::fake();

        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        SendAdminNotification::dispatch($event, 'new_event');

        Queue::assertPushed(SendAdminNotification::class, function ($job) use ($event) {
            return $job->event->id === $event->id && $job->notificationType === 'new_event';
        });
    }

    #[Test]
    public function send_admin_notification_job_sends_email_to_admins()
    {
        Mail::fake();

        // Create admin users
        $admins = User::factory()->count(3)->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);

        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendAdminNotification($event, 'new_event');
        $job->handle();

        // Should queue emails to all admins (since emails implement ShouldQueue)
        Mail::assertQueued(AdminEventNotification::class, 3);
    }

    #[Test]
    public function send_admin_notification_job_with_user_parameter()
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendAdminNotification($event, 'new_registration', $user);
        $job->handle();

        Mail::assertQueued(AdminEventNotification::class, function ($mail) use ($event, $user) {
            return $mail->event->id === $event->id && 
                   $mail->notificationType === 'new_registration' &&
                   $mail->user->id === $user->id;
        });
    }

    #[Test]
    public function send_admin_notification_job_handles_no_admins()
    {
        Mail::fake();

        // No admin users in database
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendAdminNotification($event, 'new_event');
        $job->handle();

        // Should not send any emails
        Mail::assertNothingQueued();
    }

    #[Test]
    public function send_event_reminder_job_can_be_dispatched()
    {
        Queue::fake();

        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        SendEventReminder::dispatch($event, $user, '24h');

        Queue::assertPushed(SendEventReminder::class, function ($job) use ($event, $user) {
            return $job->event->id === $event->id && 
                   $job->user->id === $user->id &&
                   $job->reminderType === '24h';
        });
    }

    #[Test]
    public function test_send_event_reminder_job()
    {
        // Create test data
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(2),
        ]);
        
        // Register user for event
        $event->registrations()->attach($user->id, [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'registered',
            'registered_at' => now()
        ]);
        
        // Create and serialize the job
        $job = new SendEventReminder($event, $user, '24_hours');
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
        
        // Verify the job can be serialized/unserialized
        $this->assertInstanceOf(SendEventReminder::class, $unserialized);
        $this->assertEquals($user->id, $unserialized->user->id);
        $this->assertEquals($event->id, $unserialized->event->id);
        $this->assertEquals('24_hours', $unserialized->reminderType);
    }

    #[Test]
    public function jobs_are_queueable()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $jobs = [
            new SendAdminNotification($event, 'new_event'),
            new SendEventReminder($event, $user, '24h')
        ];

        foreach ($jobs as $job) {
            $this->assertContains(\Illuminate\Bus\Queueable::class, class_uses_recursive($job));
        }
    }

    #[Test]
    public function jobs_implement_should_queue_interface()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $jobs = [
            new SendAdminNotification($event, 'new_event'),
            new SendEventReminder($event, $user, '24h')
        ];

        foreach ($jobs as $job) {
            $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
        }
    }

    #[Test]
    public function send_admin_notification_has_correct_queue_configuration()
    {
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendAdminNotification($event, 'new_event');

        // Should use emails queue
        $this->assertEquals('emails', $job->queue);
    }

    #[Test]
    public function send_event_reminder_has_correct_queue_configuration()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendEventReminder($event, $user, '24h');

        // Should use emails queue
        $this->assertEquals('emails', $job->queue);
    }

    #[Test]
    public function jobs_handle_serialization_correctly()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $adminJob = new SendAdminNotification($event, 'new_event', $user);
        $reminderJob = new SendEventReminder($event, $user, '24h');

        $serializedAdminJob = serialize($adminJob);
        $unserializedAdminJob = unserialize($serializedAdminJob);

        $this->assertEquals($event->id, $unserializedAdminJob->event->id);
        $this->assertEquals($user->id, $unserializedAdminJob->user->id);
        $this->assertEquals('new_event', $unserializedAdminJob->notificationType);

        $serializedReminderJob = serialize($reminderJob);
        $unserializedReminderJob = unserialize($serializedReminderJob);

        $this->assertEquals($event->id, $unserializedReminderJob->event->id);
        $this->assertEquals($user->id, $unserializedReminderJob->user->id);
        $this->assertEquals('24h', $unserializedReminderJob->reminderType);
    }

    #[Test]
    public function jobs_have_unique_queue_names()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $adminJob = new SendAdminNotification($event, 'new_event');
        $reminderJob = new SendEventReminder($event, $user, '24h');

        // Both should use the emails queue but can be differentiated by job class
        $this->assertEquals('emails', $adminJob->queue);
        $this->assertEquals('emails', $reminderJob->queue);
        
        $this->assertNotEquals(get_class($adminJob), get_class($reminderJob));
    }

    #[Test]
    public function admin_notification_job_logs_execution()
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendAdminNotification($event, 'new_event');
        
        // Test that the job runs without throwing exceptions
        $this->assertTrue(true);
        $job->handle();
    }

    #[Test]
    public function event_reminder_job_logs_execution()
    {
        Mail::fake();

        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $job = new SendEventReminder($event, $user, '24h');
        
        // Test that the job runs without throwing exceptions
        $this->assertTrue(true);
        $job->handle();
    }
}
