<?php

namespace App\Console\Commands;

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email? : Email address to send test to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'test@example.com';
        
        $this->info("Testing email functionality...");
        $this->info("Email will be sent to: {$email}");
        
        try {
            // Test basic mail configuration
            $this->info("Checking mail configuration...");
            $mailer = config('mail.default');
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $username = config('mail.mailers.smtp.username');
            
            $this->info("Mail Driver: {$mailer}");
            $this->info("SMTP Host: {$host}");
            $this->info("SMTP Port: {$port}");
            $this->info("SMTP Username: {$username}");
            
            // Create a test user
            $testUser = [
                'name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $email,
            ];
            
            // Test verification URL
            $verificationUrl = 'https://example.com/verify?token=test-token';
            
            $this->info("Sending test email...");
            
            // Send email
            Mail::to($email)->send(new EmailVerification($testUser, $verificationUrl));
            
            $this->info("✅ Email sent successfully!");
            
        } catch (\Exception $e) {
            $this->error("❌ Email failed to send!");
            $this->error("Error: " . $e->getMessage());
            Log::error('Email test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
