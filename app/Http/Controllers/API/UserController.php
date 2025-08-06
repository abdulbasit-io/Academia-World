<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FileStorageService;
use App\Traits\HandlesBase64Uploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * @OA\Tag(
 *     name="User Profile",
 *     description="User profile management endpoints"
 * )
 */
class UserController extends Controller
{
    use HandlesBase64Uploads;
    /**
     * @OA\Get(
     *     path="/api/v1/profile",
     *     summary="Get current user profile",
     *     description="Retrieve the authenticated user's profile information",
     *     operationId="getUserProfile",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="full_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="institution", type="string"),
     *                 @OA\Property(property="department", type="string", nullable=true),
     *                 @OA\Property(property="position", type="string", nullable=true),
     *                 @OA\Property(property="bio", type="string", nullable=true),
     *                 @OA\Property(property="website", type="string", nullable=true),
     *                 @OA\Property(property="phone", type="string", nullable=true),
     *                 @OA\Property(property="avatar", type="string", nullable=true),
     *                 @OA\Property(property="social_links", type="object", nullable=true),
     *                 @OA\Property(property="account_status", type="string"),
     *                 @OA\Property(property="is_admin", type="boolean"),
     *                 @OA\Property(property="hosted_events_count", type="integer"),
     *                 @OA\Property(property="registered_events_count", type="integer"),
     *                 @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            Log::info('User profile retrieved', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'institution' => $user->institution,
                    'department' => $user->department,
                    'position' => $user->position,
                    'bio' => $user->bio,
                    'website' => $user->website,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
                    'social_links' => $user->social_links,
                    'account_status' => $user->account_status,
                    'is_admin' => $user->isAdmin(),
                    'hosted_events_count' => $user->hostedEvents()->count(),
                    'registered_events_count' => $user->registeredEvents()->count(),
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profile retrieval failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve profile',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/profile",
     *     summary="Update user profile",
     *     description="Update the authenticated user's profile information",
     *     operationId="updateUserProfile",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", maxLength=255),
     *             @OA\Property(property="last_name", type="string", maxLength=255),
     *             @OA\Property(property="institution", type="string", maxLength=255),
     *             @OA\Property(property="department", type="string", maxLength=255),
     *             @OA\Property(property="position", type="string", maxLength=255),
     *             @OA\Property(property="bio", type="string", maxLength=1000),
     *             @OA\Property(property="website", type="string", format="url"),
     *             @OA\Property(property="phone", type="string", maxLength=20),
     *             @OA\Property(
     *                 property="social_links",
     *                 type="object",
     *                 @OA\Property(property="twitter", type="string"),
     *                 @OA\Property(property="linkedin", type="string"),
     *                 @OA\Property(property="orcid", type="string"),
     *                 @OA\Property(property="researchgate", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'institution' => 'sometimes|required|string|max:255',
            'department' => 'sometimes|nullable|string|max:255',
            'position' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string|max:1000',
            'website' => 'sometimes|nullable|url|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'social_links' => 'sometimes|nullable|array',
            'social_links.twitter' => 'sometimes|nullable|url',
            'social_links.linkedin' => 'sometimes|nullable|url',
            'social_links.github' => 'sometimes|nullable|url',
            'social_links.orcid' => 'sometimes|nullable|url',
            'social_links.researchgate' => 'sometimes|nullable|url',
        ]);

        if ($validator->fails()) {
            Log::warning('Profile update validation failed', [
                'user_id' => Auth::id(),
                'errors' => $validator->errors()->toArray()
            ]);

            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $updateData = $request->only([
                'first_name', 'last_name', 'institution', 'department', 
                'position', 'bio', 'website', 'phone', 'social_links'
            ]);

            // Update name field when first_name or last_name changes
            if (isset($updateData['first_name']) || isset($updateData['last_name'])) {
                $firstName = $updateData['first_name'] ?? $user->first_name;
                $lastName = $updateData['last_name'] ?? $user->last_name;
                $updateData['name'] = $firstName . ' ' . $lastName;
            }

            $user->update($updateData);

            Log::info('User profile updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData)
            ]);

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => [
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'institution' => $user->institution,
                    'department' => $user->department,
                    'position' => $user->position,
                    'bio' => $user->bio,
                    'website' => $user->website,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
                    'social_links' => $user->social_links,
                    'account_status' => $user->account_status,
                    'is_admin' => $user->isAdmin(),
                    'hosted_events_count' => $user->hostedEvents()->count(),
                    'registered_events_count' => $user->registeredEvents()->count(),
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update profile',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/profile/avatar",
     *     summary="Upload user avatar",
     *     description="Upload and update the authenticated user's avatar image",
     *     operationId="updateUserAvatar",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="avatar",
    *                     type="string",
     *                     format="binary",
     *                     description="Avatar image file (JPEG, PNG, JPG, GIF, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Avatar updated successfully"),
     *             @OA\Property(property="avatar_url", type="string", example="https://example.com/storage/avatars/user123.jpg")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        // Debug logging to understand what's being received
        Log::info('Avatar upload request received', [
            'user_id' => Auth::id(),
            'content_type' => $request->header('Content-Type'),
            'has_file' => $request->hasFile('avatar'),
            'has_avatar_input' => $request->has('avatar'),
            'request_method' => $request->method(),
            'all_keys' => array_keys($request->all()),
            'files_keys' => array_keys($request->allFiles()),
            'raw_content' => substr($request->getContent(), 0, 200), // First 200 chars
            'is_json' => $request->isJson(),
            'content_length' => $request->header('Content-Length'),
        ]);

        // Handle both multipart/form-data and JSON with base64
        $avatarFile = null;
        
        if ($request->hasFile('avatar')) {
            // Traditional file upload
            $avatarFile = $request->file('avatar');
        } elseif ($request->has('avatar') && is_string($request->input('avatar'))) {
            // Base64 upload
            $base64Data = $request->input('avatar');
            if ($this->isBase64Image($base64Data)) {
                try {
                    $avatarFile = $this->createUploadedFileFromBase64($base64Data, 'avatar.jpg');
                } catch (\Exception $e) {
                    Log::warning('Failed to process base64 avatar', [
                        'user_id' => Auth::id(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if (!$avatarFile) {
            Log::warning('No valid avatar file found in request', [
                'user_id' => Auth::id(),
                'content_type' => $request->header('Content-Type'),
                'has_file' => $request->hasFile('avatar'),
                'has_avatar_input' => $request->has('avatar'),
                'avatar_type' => $request->has('avatar') ? gettype($request->input('avatar')) : 'none'
            ]);

            return response()->json([
                'message' => 'Validation errors',
                'errors' => [
                    'avatar' => ['The avatar field is required and must be a valid image file or base64 encoded image.']
                ],
                'debug' => [
                    'content_type' => $request->header('Content-Type'),
                    'has_file' => $request->hasFile('avatar'),
                    'has_input' => $request->has('avatar'),
                    'all_keys' => array_keys($request->all()),
                    'files_keys' => array_keys($request->allFiles()),
                ]
            ], 422);
        }

        // Validate the file
        $validator = Validator::make(['avatar' => $avatarFile], [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            Log::warning('Avatar upload validation failed', [
                'user_id' => Auth::id(),
                'errors' => $validator->errors()->toArray(),
                'file_info' => [
                    'original_name' => $avatarFile->getClientOriginalName(),
                    'mime_type' => $avatarFile->getMimeType(),
                    'size' => $avatarFile->getSize(),
                ]
            ]);

            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $fileStorageService = new FileStorageService();

            // Delete old avatar if exists
            if ($user->avatar) {
                $fileStorageService->delete($user->avatar);
                Log::info('Old avatar deleted', [
                    'user_id' => $user->id,
                    'old_avatar' => $user->avatar
                ]);
            }

            // Process and store new avatar
            $avatarFile = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $avatarFile->getClientOriginalExtension();
            $path = 'avatars/' . $filename;
            
            // Store image with resizing (300x300)
            $avatarUrl = $fileStorageService->storeImage($avatarFile, $path, [
                'width' => 300,
                'height' => 300,
                'fit' => 'cover'
            ]);

            // Update user avatar URL (store complete URL, not path)
            $user->update(['avatar' => $avatarUrl]);

            Log::info('Avatar uploaded successfully', [
                'user_id' => $user->id,
                'avatar_url' => $avatarUrl,
                'file_size' => $avatarFile->getSize()
            ]);

            return response()->json([
                'message' => 'Avatar updated successfully',
                'avatar_url' => $avatarUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Avatar upload failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to upload avatar',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/profile/avatar",
     *     summary="Delete user avatar",
     *     description="Delete the authenticated user's avatar image",
     *     operationId="deleteUserAvatar",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Avatar deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Avatar deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="No avatar to delete"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->avatar) {
                return response()->json([
                    'message' => 'No avatar to delete'
                ], 400);
            }

            $fileStorageService = new FileStorageService();

            // Delete avatar file from storage
            $fileStorageService->delete($user->avatar);
            
            // Update user record
            $oldAvatar = $user->avatar;
            $user->update(['avatar' => null]);

            Log::info('Avatar deleted successfully', [
                'user_id' => $user->id,
                'deleted_avatar' => $oldAvatar
            ]);

            return response()->json([
                'message' => 'Avatar deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Avatar deletion failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to delete avatar',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/profile/stats",
     *     summary="Get user statistics",
     *     description="Get detailed statistics for the authenticated user",
     *     operationId="getUserStats",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="hosted_events_total", type="integer"),
     *                 @OA\Property(property="hosted_events_upcoming", type="integer"),
     *                 @OA\Property(property="hosted_events_completed", type="integer"),
     *                 @OA\Property(property="registered_events_total", type="integer"),
     *                 @OA\Property(property="registered_events_upcoming", type="integer"),
     *                 @OA\Property(property="total_attendees_hosted", type="integer"),
     *                 @OA\Property(property="profile_completion", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

                        // Get registered events for the user
            $registeredEvents = $user->registeredEvents()->wherePivot('status', 'registered');

            $stats = [
                'hosted_events_total' => $user->hostedEvents()->count(),
                'hosted_events_upcoming' => $user->hostedEvents()->where('start_date', '>', now())->count(),
                'hosted_events_completed' => $user->hostedEvents()->where('status', 'completed')->count(),
                'registered_events_total' => $registeredEvents->count(),
                'registered_events_upcoming' => $registeredEvents->where('start_date', '>', now())->count(),
                'total_attendees_hosted' => $user->hostedEvents()
                    ->with('registrations')
                    ->get()
                    ->sum(function($event) {
                        return $event->registrations->count();
                    }),
                'profile_completion' => $this->calculateProfileCompletion($user)
            ];

            Log::info('User statistics retrieved', [
                'user_id' => $user->id,
                'stats' => $stats
            ]);

            return response()->json([
                'message' => 'Statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Statistics retrieval failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve statistics',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * Check if the given string is a valid base64-encoded image.
     */
    private function isBase64Image(string $data): bool
    {
        // Check for data URI scheme
        if (preg_match('/^data:image\/(\w+);base64,/', $data)) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        // Check if valid base64
        if (base64_decode($data, true) === false) {
            return false;
        }
        // Try to get image size from decoded data
        $imageData = base64_decode($data);
        return @getimagesizefromstring($imageData) !== false;
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(User $user): int
    {
        $fields = [
            'first_name', 'last_name', 'email', 'institution', 
            'department', 'position', 'bio', 'avatar'
        ];
        
        $completed = 0;
        $total = count($fields);

        foreach ($fields as $field) {
            if (!empty($user->$field)) {
                $completed++;
            }
        }

        return (int) round(($completed / $total) * 100);
    }
}
