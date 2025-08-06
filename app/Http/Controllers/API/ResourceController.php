<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventResource;
use App\Services\FileStorageService;
use App\Traits\HandlesBase64Uploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;

/**
 * @OA\Tag(
 *     name="Resources",
 *     description="Event resource management operations"
 * )
 */
class ResourceController extends Controller
{
    use HandlesBase64Uploads;
    /**
     * Retrieve academic resources for a specific event
     * 
     * @OA\Get(
     *     path="/api/v1/events/{event}/resources",
     *     tags={"Resources"},
     *     summary="Retrieve academic resources for a specific event",
     *     description="Fetches all academic resources (presentations, papers, recordings, datasets, etc.) associated with an event. Access control ensures users only see resources they're permitted to view based on their registration status and the resource's visibility settings. Resources include presentations, research papers, recordings, datasets, and other academic materials.",
     *     operationId="getEventResources",
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID identifier",
     *         required=true,
     *         @OA\Schema(
     *             type="string", 
     *             format="uuid",
     *             example="550e8400-e29b-41d4-a716-446655440000"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter resources by academic resource type",
     *         required=false,
     *         @OA\Schema(
     *             type="string", 
     *             enum={"presentation", "paper", "recording", "agenda", "poster", "handout", "dataset", "code", "other"},
     *             example="presentation"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="public_only",
     *         in="query",
     *         description="Show only publicly accessible resources (no authentication required)",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             default=false,
     *             example=true
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="downloadable_only",
     *         in="query",
     *         description="Filter to show only downloadable resources",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             default=false,
     *             example=false
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in resource titles and descriptions",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             maxLength=255,
     *             example="machine learning slides"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort resources by field",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"created_at", "title", "download_count", "view_count", "file_size"},
     *             default="created_at",
     *             example="download_count"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order direction",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             default="desc",
     *             example="desc"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Academic resources retrieved successfully with access control applied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event resources retrieved successfully"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array", 
     *                 description="Array of accessible event resources",
     *                 @OA\Items(ref="#/components/schemas/EventResource")
     *             ),
     *             @OA\Property(
     *                 property="access_info",
     *                 type="object",
     *                 description="Information about user's access level",
     *                 @OA\Property(property="user_role", type="string", example="registered_attendee", enum={"guest", "registered_attendee", "event_host", "admin"}),
     *                 @OA\Property(property="can_upload", type="boolean", example=false, description="Whether user can upload resources"),
     *                 @OA\Property(property="total_accessible", type="integer", example=8, description="Total resources user can access"),
     *                 @OA\Property(property="total_public", type="integer", example=5, description="Public resources available to all")
     *             ),
     *             @OA\Property(
     *                 property="resource_summary",
     *                 type="object",
     *                 description="Summary of available resource types",
     *                 @OA\Property(property="presentations", type="integer", example=3),
     *                 @OA\Property(property="papers", type="integer", example=2),
     *                 @OA\Property(property="recordings", type="integer", example=1),
     *                 @OA\Property(property="datasets", type="integer", example=1),
     *                 @OA\Property(property="other", type="integer", example=1)
     *             ),
     *             @OA\Property(
     *                 property="helpful_links",
     *                 type="object",
     *                 description="Related endpoints for resource management",
     *                 @OA\Property(property="upload_resource", type="string", example="/api/v1/events/550e8400-e29b-41d4-a716-446655440000/resources"),
     *                 @OA\Property(property="event_details", type="string", example="/api/v1/events/550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="discussion_forums", type="string", example="/api/v1/events/550e8400-e29b-41d4-a716-446655440000/forums")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - insufficient permissions to view event resources",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You don't have permission to view resources for this event"),
     *             @OA\Property(property="error_code", type="string", example="INSUFFICIENT_ACCESS"),
     *             @OA\Property(
     *                 property="access_requirements",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Event registration required", "Event must be published", "Account verification needed"}
     *             ),
     *             @OA\Property(
     *                 property="suggestions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Register for the event to access private resources", "View only public resources by adding ?public_only=true", "Contact the event organizer for special access"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found or not accessible",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event not found"),
     *             @OA\Property(property="error_code", type="string", example="EVENT_NOT_FOUND"),
     *             @OA\Property(
     *                 property="possible_reasons",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Event UUID does not exist", "Event has been deleted", "Event is private and you don't have access"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error while retrieving resources",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Build query based on access permissions
            $query = $event->resources()->with('uploadedBy:id,first_name,last_name')->active();
            
            // Apply filters
            if ($request->has('type')) {
                $query->ofType($request->get('type'));
            }
            
            // Handle public vs private access
            if ($request->boolean('public_only') || !$user) {
                $query->public();
            } else {
                // If user is authenticated, show resources they can access
                $isEventHost = $user && $user->id === $event->host_id;
                $isAdmin = $user && $user->isAdmin();
                $isRegistered = $user && $event->registrations()
                    ->wherePivot('user_id', $user->id)
                    ->wherePivot('status', 'registered')
                    ->exists();
                
                if (!$isEventHost && !$isAdmin) {
                    $query->where(function($q) use ($isRegistered) {
                        $q->where('is_public', true)
                          ->orWhere(function($subQ) use ($isRegistered) {
                              $subQ->where('requires_registration', false);
                              if ($isRegistered) {
                                  $subQ->orWhere('requires_registration', true);
                              }
                          });
                    });
                }
            }
            
            $resources = $query->orderBy('created_at', 'desc')->get();
            
            // Increment view count for each resource
            if ($user) {
                $resources->each(function($resource) {
                    $resource->incrementViewCount();
                });
            }
            
            return response()->json([
                'message' => 'Event resources retrieved successfully',
                'data' => $resources->map(function($resource) {
                    return [
                        'uuid' => $resource->uuid,
                        'title' => $resource->title,
                        'description' => $resource->description,
                        'original_filename' => $resource->original_filename,
                        'file_type' => $resource->file_type,
                        'file_size' => $resource->file_size,
                        'file_size_formatted' => $resource->getFileSizeFormatted(),
                        'file_url' => $resource->file_path, // Public URL to the resource file
                        'resource_type' => $resource->resource_type,
                        'is_public' => $resource->is_public,
                        'is_downloadable' => $resource->is_downloadable,
                        'download_count' => $resource->download_count,
                        'view_count' => $resource->view_count,
                        'uploaded_by' => $resource->uploadedBy,
                        'created_at' => $resource->created_at,
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Resource listing failed', [
                'event_id' => $event->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to retrieve resources',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/events/{event}/resources",
     *     tags={"Resources"},
     *     summary="Upload event resource",
     *     description="Upload a new resource for an event (host only)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description="Resource file"),
     *                 @OA\Property(property="title", type="string", maxLength=255),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="resource_type", type="string", enum={"presentation", "paper", "recording", "agenda", "other"}),
     *                 @OA\Property(property="is_public", type="boolean"),
     *                 @OA\Property(property="is_downloadable", type="boolean"),
     *                 @OA\Property(property="requires_registration", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Resource uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/EventResource")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized to upload resources"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        
        // Check permissions - only event host or admin can upload
        if (!$user) {
            return response()->json([
                'message' => 'You are not authorized to upload resources for this event'
            ], 403);
        }
        
        $isEventHost = $user->id === $event->host_id;
        $isAdmin = $user->isAdmin();
        
        if (!$isEventHost && !$isAdmin) {
            return response()->json([
                'message' => 'You are not authorized to upload resources for this event'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200', // 50MB max
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'resource_type' => 'required|string|in:presentation,paper,recording,agenda,other',
            'is_public' => 'boolean',
            'is_downloadable' => 'boolean',
            'requires_registration' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Handle both multipart/form-data and JSON with base64
            $file = null;
            
            if ($request->hasFile('file')) {
                // Traditional file upload
                $file = $request->file('file');
            } elseif ($request->has('file') && is_string($request->input('file'))) {
                // Base64 upload
                $base64Data = $request->input('file');
                if ($this->isValidBase64($base64Data)) {
                    try {
                        $file = $this->createUploadedFileFromBase64($base64Data, 'resource.pdf');
                    } catch (\Exception $e) {
                        Log::warning('Failed to process base64 resource', [
                            'user_id' => Auth::id(),
                            'event_id' => $event->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            if (!$file) {
                return response()->json([
                    'message' => 'Validation errors',
                    'errors' => [
                        'file' => ['The file field is required and must be a valid file or base64 encoded file.']
                    ]
                ], 422);
            }
            
            $originalFilename = $file->getClientOriginalName();
            $fileExtension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
            
            // Generate unique filename
            $filename = 'resource_' . $event->id . '_' . Str::uuid() . '.' . $fileExtension;
            
            // Validate file type
            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/gif',
                'video/mp4',
                'video/avi',
                'video/quicktime',
                'audio/mpeg',
                'audio/wav',
                'application/zip',
                'application/x-rar-compressed'
            ];
            
            if (!in_array($mimeType, $allowedMimes)) {
                return response()->json([
                    'message' => 'File type not allowed',
                    'error' => 'Please upload a valid document, image, video, or audio file.'
                ], 422);
            }
            
            // Store file
            $fileStorageService = new FileStorageService();
            $path = 'event-resources/' . $filename;
            $fileUrl = $fileStorageService->store($file, $path);
            
            // Create resource record
            $resource = EventResource::create([
                'event_id' => $event->id,
                'uploaded_by' => $user->id,
                'title' => $request->get('title') ?: pathinfo($originalFilename, PATHINFO_FILENAME),
                'description' => $request->get('description'),
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_path' => $fileUrl, // Store the complete URL instead of path
                'file_type' => $fileExtension,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'resource_type' => $request->get('resource_type'),
                'is_public' => $request->boolean('is_public', false),
                'is_downloadable' => $request->boolean('is_downloadable', true),
                'requires_registration' => $request->boolean('requires_registration', true),
            ]);
            
            Log::info('Resource uploaded successfully', [
                'resource_id' => $resource->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'filename' => $originalFilename,
                'file_size' => $fileSize
            ]);
            
            return response()->json([
                'message' => 'Resource uploaded successfully',
                'data' => [
                    'uuid' => $resource->uuid,
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'original_filename' => $resource->original_filename,
                    'file_type' => $resource->file_type,
                    'file_size' => $resource->file_size,
                    'file_size_formatted' => $resource->getFileSizeFormatted(),
                    'file_url' => $resource->file_path, // Public URL to the resource file
                    'resource_type' => $resource->resource_type,
                    'is_public' => $resource->is_public,
                    'is_downloadable' => $resource->is_downloadable,
                    'requires_registration' => $resource->requires_registration,
                    'created_at' => $resource->created_at,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Resource upload failed', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to upload resource',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/resources/{resource}",
     *     tags={"Resources"},
     *     summary="Get resource details",
     *     description="Retrieve detailed information about a specific resource",
     *     @OA\Parameter(
     *         name="resource",
     *         in="path",
     *         description="Resource UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resource details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/EventResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=403, description="Access denied")
     * )
     */
    public function show(Request $request, EventResource $resource): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Check if user can access this resource
            if (!$resource->canBeAccessedBy($user)) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource'
                ], 403);
            }
            
            // Increment view count
            if ($user) {
                $resource->incrementViewCount();
            }
            
            $resource->load(['event:id,title', 'uploadedBy:id,first_name,last_name']);
            
            return response()->json([
                'message' => 'Resource details retrieved successfully',
                'data' => [
                    'uuid' => $resource->uuid,
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'original_filename' => $resource->original_filename,
                    'file_type' => $resource->file_type,
                    'file_size' => $resource->file_size,
                    'file_size_formatted' => $resource->getFileSizeFormatted(),
                    'file_url' => $resource->file_path, // Public URL to the resource file
                    'resource_type' => $resource->resource_type,
                    'is_public' => $resource->is_public,
                    'is_downloadable' => $resource->is_downloadable,
                    'requires_registration' => $resource->requires_registration,
                    'download_count' => $resource->download_count,
                    'view_count' => $resource->view_count,
                    'event' => $resource->event,
                    'uploaded_by' => $resource->uploadedBy,
                    'created_at' => $resource->created_at,
                    'updated_at' => $resource->updated_at,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Resource show failed', [
                'resource_id' => $resource->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to retrieve resource details',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/resources/{resource}",
     *     tags={"Resources"},
     *     summary="Update resource details",
     *     description="Update resource metadata (host/admin only)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="resource",
     *         in="path",
     *         description="Resource UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="resource_type", type="string", enum={"presentation", "paper", "recording", "agenda", "other"}),
     *             @OA\Property(property="is_public", type="boolean"),
     *             @OA\Property(property="is_downloadable", type="boolean"),
     *             @OA\Property(property="requires_registration", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resource updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/EventResource")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function update(Request $request, EventResource $resource): JsonResponse
    {
        $user = $request->user();
        
        // Check permissions - only event host or admin can update
        if (!$user || ($user->id !== $resource->event->host_id && !$user->isAdmin())) {
            return response()->json([
                'message' => 'You are not authorized to update this resource'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'resource_type' => 'sometimes|string|in:presentation,paper,recording,agenda,other',
            'is_public' => 'boolean',
            'is_downloadable' => 'boolean',
            'requires_registration' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $resource->update($request->only([
                'title', 'description', 'resource_type', 
                'is_public', 'is_downloadable', 'requires_registration'
            ]));
            
            Log::info('Resource updated successfully', [
                'resource_id' => $resource->id,
                'event_id' => $resource->event_id,
                'user_id' => $user->id,
                'updated_fields' => array_keys($request->only([
                    'title', 'description', 'resource_type', 
                    'is_public', 'is_downloadable', 'requires_registration'
                ]))
            ]);
            
            return response()->json([
                'message' => 'Resource updated successfully',
                'data' => [
                    'uuid' => $resource->uuid,
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'file_url' => $resource->file_path, // Public URL to the resource file
                    'resource_type' => $resource->resource_type,
                    'is_public' => $resource->is_public,
                    'is_downloadable' => $resource->is_downloadable,
                    'requires_registration' => $resource->requires_registration,
                    'updated_at' => $resource->updated_at,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Resource update failed', [
                'resource_id' => $resource->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to update resource',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/resources/{resource}",
     *     tags={"Resources"},
     *     summary="Delete resource",
     *     description="Delete a resource and its file (host/admin only)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="resource",
     *         in="path",
     *         description="Resource UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resource deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy(Request $request, EventResource $resource): JsonResponse
    {
        $user = $request->user();
        
        // Check permissions - only event host or admin can delete
        if (!$user || ($user->id !== $resource->event->host_id && !$user->isAdmin())) {
            return response()->json([
                'message' => 'You are not authorized to delete this resource'
            ], 403);
        }
        
        try {
            $fileStorageService = new FileStorageService();
            
            // Delete file from storage
            if ($resource->file_path) {
                $fileStorageService->delete($resource->file_path);
            }
            
            // Soft delete the resource
            $resource->delete();
            
            Log::info('Resource deleted successfully', [
                'resource_id' => $resource->id,
                'event_id' => $resource->event_id,
                'user_id' => $user->id,
                'filename' => $resource->original_filename
            ]);
            
            return response()->json([
                'message' => 'Resource deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Resource deletion failed', [
                'resource_id' => $resource->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete resource',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/resources/{resource}/download",
     *     tags={"Resources"},
     *     summary="Download resource file",
     *     description="Download a resource file if permitted",
     *     @OA\Parameter(
     *         name="resource",
     *         in="path",
     *         description="Resource UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File download",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Download not permitted"),
     *     @OA\Response(response=404, description="Resource or file not found")
     * )
     */
    public function download(Request $request, EventResource $resource)
    {
        try {
            $user = $request->user();
            
            // Check if user can access this resource
            if (!$resource->canBeAccessedBy($user)) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource'
                ], 403);
            }
            
            // Check if resource is downloadable
            if (!$resource->is_downloadable) {
                return response()->json([
                    'message' => 'This resource is not available for download'
                ], 403);
            }
            
            // Check if file_url exists
            if (!$resource->file_path) {
                return response()->json([
                    'message' => 'File URL not found'
                ], 404);
            }
            
            // Increment download count
            $resource->incrementDownloadCount();
            
            Log::info('Resource downloaded', [
                'resource_id' => $resource->id,
                'event_id' => $resource->event_id,
                'user_id' => $user?->id,
                'filename' => $resource->original_filename,
                'file_path' => $resource->file_path
            ]);
            
            // Handle both URL and path formats (for backward compatibility and testing)
            if (str_starts_with($resource->file_path, 'http')) {
                // Full URL - redirect to the file URL
                return redirect($resource->file_path);
            } else {
                // Relative path (legacy format or test) - serve from local storage
                $filePath = Storage::disk('public')->path($resource->file_path);
                
                if (!Storage::disk('public')->exists($resource->file_path)) {
                    return response()->json([
                        'message' => 'File not found'
                    ], 404);
                }
                
                return response()->download($filePath, $resource->original_filename, [
                    'Content-Type' => $resource->mime_type ?? 'application/octet-stream',
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Resource download failed', [
                'resource_id' => $resource->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to download resource',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * Check if the given string is valid base64 data
     */
    private function isValidBase64(string $data): bool
    {
        // Check for data URI scheme
        if (preg_match('/^data:([^;]+);base64,/', $data)) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        // Check if valid base64
        return base64_decode($data, true) !== false;
    }
}
