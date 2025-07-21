<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmailVerificationToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Mail\EmailVerification;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationTest extends TestCase
{
    #[Test]
    public function user_can_register_successfully()
    {
        Mail::fake();

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@university.edu',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'institution' => 'University of Technology',
            'department' => 'Computer Science',
            'position' => 'Professor'
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData, $this->getApiHeaders());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => [
                'uuid',
                'name',
                'email',
                'institution',
                'account_status'
            ]
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@university.edu',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'institution' => 'University of Technology',
            'account_status' => 'pending'
        ]);

        // Verify email verification was queued (since emails implement ShouldQueue)
        Mail::assertQueued(EmailVerification::class);
    }

    #[Test]
    public function registration_fails_with_invalid_data()
    {
        $invalidData = [
            'first_name' => '',
            'email' => 'invalid-email',
            'password' => '123', // too short
            'password_confirmation' => 'different-password'
        ];

        $response = $this->postJson('/api/v1/auth/register', $invalidData, $this->getApiHeaders());

        $this->assertValidationError($response, [
            'first_name', 'email', 'password', 'institution'
        ]);
    }

    #[Test]
    public function registration_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'john.doe@university.edu']);

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@university.edu',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'institution' => 'University of Technology'
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData, $this->getApiHeaders());

        $this->assertValidationError($response, ['email']);
    }

    #[Test]
    public function user_can_login_successfully()
    {
        $user = User::factory()->create([
            'email' => 'john.doe@university.edu',
            'password' => Hash::make('password123'),
            'account_status' => 'active',
            'email_verified_at' => now()
        ]);

        $loginData = [
            'email' => 'john.doe@university.edu',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData, $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'access_token',
            'token_type',
            'user' => [
                'uuid',
                'name',
                'email',
                'account_status',
                'is_admin'
            ]
        ]);

        // Verify the token works by accessing a protected endpoint
        $token = $response->json('access_token');
        $this->assertNotEmpty($token);
        
        // Test the token by accessing the user profile endpoint
        $userResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->getJson('/api/v1/auth/user');
        
        $userResponse->assertStatus(200);
        $userResponse->assertJson([
            'user' => [
                'email' => $user->email
            ]
        ]);
    }

    #[Test]
    public function login_fails_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'john.doe@university.edu',
            'password' => Hash::make('password123'),
            'account_status' => 'active'
        ]);

        $loginData = [
            'email' => 'john.doe@university.edu',
            'password' => 'wrong-password'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData, $this->getApiHeaders());

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid credentials']);
    }

    #[Test]
    public function login_fails_for_unverified_email()
    {
        User::factory()->create([
            'email' => 'john.doe@university.edu',
            'password' => Hash::make('password123'),
            'account_status' => 'pending',
            'email_verified_at' => null
        ]);

        $loginData = [
            'email' => 'john.doe@university.edu',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData, $this->getApiHeaders());

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Please verify your email address to activate your account.',
            'action_required' => 'email_verification'
        ]);
    }

    #[Test]
    public function user_can_logout_successfully()
    {
        $user = $this->authenticateUser();

        $response = $this->postJson('/api/v1/auth/logout', [], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out successfully']);
    }

    #[Test]
    public function authenticated_user_can_get_profile()
    {
        $user = $this->authenticateUser();

        $response = $this->getJson('/api/v1/auth/user', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => [
                'uuid',
                'name',
                'email',
                'institution',
                'department',
                'position',
                'account_status',
                'is_admin',
                'hosted_events_count',
                'registered_events_count'
            ]
        ]);
    }

    #[Test]
    public function user_can_verify_email_with_valid_token()
    {
        $user = $this->createPendingUser(['email' => 'john.doe@university.edu']);
        
        $token = EmailVerificationToken::create([
            'email' => $user->email,
            'token' => 'valid-token-123',
            'expires_at' => now()->addHour()
        ]);

        $verificationData = [
            'token' => 'valid-token-123',
            'email' => 'john.doe@university.edu'
        ];

        $response = $this->postJson('/api/v1/auth/verify-email', $verificationData, $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Email verified successfully'
        ]);

        $this->assertDatabaseMissing('email_verification_tokens', [
            'token' => 'valid-token-123'
        ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function email_verification_fails_with_invalid_token()
    {
        $verificationData = [
            'token' => 'invalid-token',
            'email' => 'john.doe@university.edu'
        ];

        $response = $this->postJson('/api/v1/auth/verify-email', $verificationData, $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid or expired verification token'
        ]);
    }

    #[Test]
    public function email_verification_fails_with_expired_token()
    {
        $user = $this->createPendingUser(['email' => 'john.doe@university.edu']);
        
        EmailVerificationToken::create([
            'email' => $user->email,
            'token' => 'expired-token-123',
            'expires_at' => now()->subHour() // Expired
        ]);

        $verificationData = [
            'token' => 'expired-token-123',
            'email' => 'john.doe@university.edu'
        ];

        $response = $this->postJson('/api/v1/auth/verify-email', $verificationData, $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Verification token has expired'
        ]);
    }

    #[Test]
    public function user_can_resend_verification_email()
    {
        Mail::fake();
        
        $user = $this->createPendingUser(['email' => 'john.doe@university.edu']);

        $response = $this->postJson('/api/v1/auth/resend-verification', [
            'email' => 'john.doe@university.edu'
        ], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Verification email sent successfully. Please check your inbox.'
        ]);

        Mail::assertQueued(EmailVerification::class);
    }

    #[Test]
    public function resend_verification_fails_for_already_verified_user()
    {
        $user = $this->authenticateUser(['email' => 'john.doe@university.edu']);

        $response = $this->postJson('/api/v1/auth/resend-verification', [
            'email' => 'john.doe@university.edu'
        ], $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Email is already verified'
        ]);
    }

    #[Test]
    public function resend_verification_fails_for_nonexistent_user()
    {
        $response = $this->postJson('/api/v1/auth/resend-verification', [
            'email' => 'nonexistent@university.edu'
        ], $this->getApiHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'User not found'
        ]);
    }
}
