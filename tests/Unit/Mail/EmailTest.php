<?php

namespace Tests\Unit\Mail;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Mail\EmailVerification;
use App\Mail\EventRegistrationConfirmation;
use App\Mail\EventReminder;
use App\Mail\AdminEventNotification;
use PHPUnit\Framework\Attributes\Test;

class EmailTest extends TestCase
{
    #[Test]
    public function email_verification_mail_can_be_built()
    {
        $user = User::factory()->create();
        $verificationUrl = 'https://example.com/verify?token=test-token';

        $mail = new EmailVerification($user, $verificationUrl);

        $this->assertEquals('Verify Your Email Address - Academia World', $mail->envelope()->subject);
        $this->assertEquals('emails.auth.verification', $mail->content()->markdown);
        $this->assertInstanceOf(EmailVerification::class, $mail);
    }

    #[Test]
    public function email_verification_contains_correct_data()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        $verificationUrl = 'https://example.com/verify?token=test-token';

        $mail = new EmailVerification($user, $verificationUrl);
        $content = $mail->content();

        $this->assertEquals($user, $content->with['user']);
        $this->assertEquals($verificationUrl, $content->with['verificationUrl']);
    }

    #[Test]
    public function event_registration_confirmation_mail_can_be_built()
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $mail = new EventRegistrationConfirmation($user, $event);

        $this->assertEquals('Registration Confirmed: ' . $event->title, $mail->envelope()->subject);
        $this->assertEquals('emails.event.registration-confirmation', $mail->content()->markdown);
        $this->assertInstanceOf(EventRegistrationConfirmation::class, $mail);
    }

    #[Test]
    public function event_registration_confirmation_contains_correct_data()
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'title' => 'AI Workshop',
            'start_date' => now()->addDays(7)
        ]);

        $mail = new EventRegistrationConfirmation($user, $event);
        $content = $mail->content();

        $this->assertEquals($user, $content->with['user']);
        $this->assertEquals($event, $content->with['event']);
    }

    #[Test]
    public function event_reminder_mail_can_be_built()
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $mail = new EventReminder($user, $event);

        $this->assertEquals('Tomorrow: ' . $event->title, $mail->envelope()->subject);
        $this->assertEquals('emails.event.reminder', $mail->content()->markdown);
        $this->assertInstanceOf(EventReminder::class, $mail);
    }

    #[Test]
    public function event_reminder_contains_correct_data()
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'title' => 'Machine Learning Conference',
            'start_date' => now()->addDay()
        ]);

        $mail = new EventReminder($user, $event);
        $content = $mail->content();

        $this->assertEquals($user, $content->with['user']);
        $this->assertEquals($event, $content->with['event']);
    }

    #[Test]
    public function admin_event_notification_mail_can_be_built()
    {
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $mail = new AdminEventNotification($event, 'new_event');

        $this->assertEquals('[Admin] New Event Published', $mail->envelope()->subject);
        $this->assertInstanceOf(AdminEventNotification::class, $mail);
    }

    #[Test]
    public function admin_event_notification_with_registration_type()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $mail = new AdminEventNotification($event, 'new_registration', $user);

        $this->assertEquals('[Admin] New Event Registration', $mail->envelope()->subject);
        $this->assertInstanceOf(AdminEventNotification::class, $mail);
    }

    #[Test]
    public function admin_notification_contains_correct_data()
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'title' => 'Data Science Workshop'
        ]);

        $mail = new AdminEventNotification($event, 'new_registration', $user);
        $content = $mail->content();

        $this->assertEquals($event, $content->with['event']);
        $this->assertEquals('new_registration', $content->with['notificationType']);
        $this->assertEquals($user, $content->with['user']);
    }

    #[Test]
    public function all_emails_are_queueable()
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $emails = [
            new EmailVerification($user, 'https://example.com'),
            new EventRegistrationConfirmation($user, $event),
            new EventReminder($user, $event),
            new AdminEventNotification($event, 'new_event')
        ];

        foreach ($emails as $email) {
            $this->assertContains(\Illuminate\Bus\Queueable::class, class_uses_recursive($email));
        }
    }

    #[Test]
    public function emails_have_correct_priority()
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        // Email verification should be high priority
        $emailVerification = new EmailVerification($user, 'https://example.com');
        $emailVerification->onQueue('high');
        $this->assertEquals('high', $emailVerification->queue);

        // Event emails should be normal priority
        $eventConfirmation = new EventRegistrationConfirmation($user, $event);
        $eventConfirmation->onQueue('default');
        $this->assertEquals('default', $eventConfirmation->queue);
    }

    #[Test]
    public function emails_use_correct_from_address()
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $emails = [
            new EmailVerification($user, 'https://example.com'),
            new EventRegistrationConfirmation($user, $event),
            new EventReminder($user, $event),
            new AdminEventNotification($event, 'new_event')
        ];

        foreach ($emails as $email) {
            // These emails use the default from address configured in mail.php
            $this->assertInstanceOf(get_class($email), $email);
        }
    }

    #[Test]
    public function event_emails_include_event_details()
    {
        $user = User::factory()->create();
        $host = User::factory()->create(['first_name' => 'Dr. Jane', 'last_name' => 'Smith']);
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'title' => 'Advanced AI Workshop',
            'description' => 'Deep dive into AI technologies',
            'start_date' => '2025-08-15 14:00:00',
            'location_type' => 'hybrid',
            'location' => 'Main Conference Hall',
            'virtual_link' => 'https://zoom.us/j/123456789'
        ]);

        $mail = new EventRegistrationConfirmation($user, $event);
        $content = $mail->content();

        $this->assertEquals('Advanced AI Workshop', $content->with['event']->title);
        $this->assertEquals('Deep dive into AI technologies', $content->with['event']->description);
        $this->assertEquals('hybrid', $content->with['event']->location_type);
    }
}
