<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationToken;
use App\Mail\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and account management"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Register a new user",
     *     description="Create a new user account with academic information",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","email","password","password_confirmation","institution"},
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@university.edu"),
     *             @OA\Property(property="password", type="string", minLength=8, example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123"),
     *             @OA\Property(property="institution", type="string", maxLength=255, example="University of Technology"),
     *             @OA\Property(property="department", type="string", maxLength=255, example="Computer Science"),
     *             @OA\Property(property="position", type="string", maxLength=255, example="Professor")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration successful. Please check your email for verification."),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@university.edu"),
     *                 @OA\Property(property="institution", type="string", example="University of Technology"),
     *                 @OA\Property(property="account_status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation errors"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Registration failed")
     * )
     */

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'institution' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'institution' => $request->institution,
                'department' => $request->department,
                'position' => $request->position,
                'account_status' => 'pending' // Requires email verification
            ]);

            event(new Registered($user));

            // Send email verification
            $this->sendVerificationEmail($user);

            return response()->json([
                'message' => 'Registration successful. Please check your email for verification.',
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'institution' => $user->institution,
                    'account_status' => $user->account_status
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticate user and return access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@university.edu"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="access_token", type="string", example="1|TOKEN_HERE"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->getAuthPassword())) {
            Log::channel('auth')->warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->isActive()) {
            // Check if email is not verified
            if (!$user->email_verified_at) {
                return response()->json([
                    'message' => 'Please verify your email address to activate your account.',
                    'action_required' => 'email_verification'
                ], 403);
            }
            
            return response()->json([
                'message' => 'Account not activated. Please contact administrator.'
            ], 403);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('academia-world-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->full_name,
                'email' => $user->email,
                'institution' => $user->institution,
                'department' => $user->department,
                'position' => $user->position,
                'account_status' => $user->account_status,
                'is_admin' => $user->isAdmin()
            ]
        ]);
    }

    /**
     * Get current user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }
        
        return response()->json([
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->full_name,
                'email' => $user->email,
                'institution' => $user->institution,
                'department' => $user->department,
                'position' => $user->position,
                'account_status' => $user->account_status,
                'is_admin' => $user->isAdmin(),
                'hosted_events_count' => $user->hostedEvents()->count(),
                'registered_events_count' => $user->registeredEvents()->count()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Authentication"},
     *     summary="User logout",
     *     description="Logout user and invalidate all access tokens",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email'
            ]);
        }

        return response()->json([
            'message' => 'Unable to send reset link'
        ], 500);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully'
            ]);
        }

        return response()->json([
            'message' => 'Password reset failed'
        ], 500);
    }

    /**
     * Verify email address
     * 
     * @OA\Post(
     *     path="/api/v1/auth/verify-email",
     *     tags={"Authentication"},
     *     summary="Verify user email address",
     *     description="Verify user email address using verification token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="abc123def456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid or expired token"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        /** @phpstan-ignore-next-line */
        $verificationToken = EmailVerificationToken::where('token', $request->token)
            ->where('email', $request->email)
            ->first();

        if (!$verificationToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification token'
            ], 400);
        }

        if ($verificationToken->expires_at < now()) {
            $verificationToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Verification token has expired'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 400);
        }

        $user->markEmailAsVerified();
        $verificationToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully'
        ]);
    }

    /**
     * Resend verification email
     * 
     * @OA\Post(
     *     path="/api/v1/auth/resend-verification",
     *     tags={"Authentication"},
     *     summary="Resend email verification",
     *     description="Resend email verification to user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@university.edu")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification email sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification email sent")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Email already verified or user not found"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            if ($user->email_verified_at) {
                return response()->json([
                    'message' => 'Email is already verified'
                ], 400);
            }

            // Send verification email
            $this->sendVerificationEmail($user);

            return response()->json([
                'message' => 'Verification email sent successfully. Please check your inbox.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resend verification email: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send verification email'
            ], 500);
        }
    }

    /**
     * Send verification email to user
     */
    private function sendVerificationEmail(User $user): void
    {
        // Delete any existing verification tokens for this email
        /** @phpstan-ignore-next-line */
        EmailVerificationToken::where('email', $user->email)->delete();

        // Create new verification token
        $token = Str::random(64);
        /** @phpstan-ignore-next-line */
        EmailVerificationToken::create([
            'email' => $user->email,
            'token' => $token,
            'expires_at' => now()->addHour(), // Token expires in 1 hour
        ]);

        // Generate verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'email.verify',
            now()->addHour(),
            ['token' => $token]
        );

        // Send email
        Mail::to($user->email)->queue(
            new EmailVerification($user, $verificationUrl)
        );
    }
}
