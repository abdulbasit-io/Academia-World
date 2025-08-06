<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Jobs\SendAdminNotification;
use App\Jobs\SendEventReminder;
use App\Mail\EventRegistrationConfirmation;
use App\Services\AnalyticsService;
use App\Services\FileStorageService;
use App\Traits\HandlesBase64Uploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * @OA\Tag(
 *     name="Events",
 *     description="Event management operations"
 * )
 */
class EventController extends Controller
{
    use HandlesBase64Uploads;
    
    public function __construct(private AnalyticsService $analyticsService)
    {
        // Constructor injection for AnalyticsService
    }
    /**
     * Browse published academic events with advanced filtering
     * 
     * @OA\Get(
     *     path="/api/v1/events",
     *     tags={"Events"},
     *     summary="Browse published academic events with advanced filtering and search",
     *     description="Retrieves a paginated list of published, public academic events with comprehensive filtering capabilities. This endpoint allows users to discover upcoming conferences, workshops, seminars, and other academic gatherings. Events are automatically filtered to show only future events that are publicly accessible.",
     *     operationId="browseAcademicEvents",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search across event titles, descriptions, locations, and tags using fuzzy matching",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             maxLength=255,
     *             example="machine learning conference"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="location_type",
     *         in="query",
     *         description="Filter events by their delivery format",
     *         required=false,
     *         @OA\Schema(
     *             type="string", 
     *             enum={"physical", "virtual", "hybrid"},
     *             example="hybrid"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter events starting from this date (inclusive). Events starting on this date or later will be included.",
     *         required=false,
     *         @OA\Schema(
     *             type="string", 
     *             format="date",
     *             example="2025-08-15"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter events ending before this date (inclusive). Events starting on this date or earlier will be included.",
     *         required=false,
     *         @OA\Schema(
     *             type="string", 
     *             format="date",
     *             example="2025-12-31"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter events by academic category or discipline",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="computer-science"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="host_institution",
     *         in="query",
     *         description="Filter events by hosting institution",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="Stanford University"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination (starts from 1)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer", 
     *             minimum=1, 
     *             default=1,
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of events per page",
     *         required=false,
     *         @OA\Schema(
     *             type="integer", 
     *             minimum=1, 
     *             maximum=50,
     *             default=15,
     *             example=15
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort events by field",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"start_date", "created_at", "title", "registration_count"},
     *             default="start_date",
     *             example="start_date"
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
     *             default="asc",
     *             example="asc"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Academic events retrieved successfully with pagination metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Events retrieved successfully"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array", 
     *                 description="Array of academic events",
     *                 @OA\Items(ref="#/components/schemas/Event")
     *             ),
     *             @OA\Property(
     *                 property="pagination", 
     *                 ref="#/components/schemas/PaginationMeta",
     *                 description="Pagination information for navigating results"
     *             ),
     *             @OA\Property(
     *                 property="filters_applied",
     *                 type="object",
     *                 description="Summary of active filters",
     *                 @OA\Property(property="search_term", type="string", nullable=true, example="machine learning"),
     *                 @OA\Property(property="location_type", type="string", nullable=true, example="hybrid"),
     *                 @OA\Property(property="date_range", type="object", nullable=true,
     *                     @OA\Property(property="from", type="string", example="2025-08-15"),
     *                     @OA\Property(property="to", type="string", example="2025-12-31")
     *                 ),
     *                 @OA\Property(property="results_count", type="integer", example=42)
     *             ),
     *             @OA\Property(
     *                 property="suggestions",
     *                 type="object",
     *                 description="Helpful suggestions for users",
     *                 @OA\Property(
     *                     property="popular_searches", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"artificial intelligence", "climate research", "digital humanities"}
     *                 ),
     *                 @OA\Property(
     *                     property="upcoming_categories", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"Computer Science", "Environmental Studies", "Medical Research"}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid query parameters or filters",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid date format. Please use YYYY-MM-DD format."),
     *             @OA\Property(property="error_code", type="string", example="INVALID_DATE_FORMAT"),
     *             @OA\Property(
     *                 property="invalid_parameters",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"date_from", "date_to"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error during event retrieval",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with(['host:id,uuid,first_name,last_name,institution'])
            ->published()
            ->public()
            ->upcoming();

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->has('location_type')) {
            $query->where('location_type', $request->get('location_type'));
        }

        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('start_date', '<=', $request->get('date_to'));
        }

        $events = $query->orderBy('start_date', 'asc')
            ->paginate(15);

        return response()->json([
            'message' => 'Events retrieved successfully',
            'data' => collect($events->items())->map(function($event) {
                return $this->transformEventData($event);
            }),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ]
        ]);
    }

    /**
     * Create a new academic event for the platform
     * 
     * @OA\Post(
     *     path="/api/v1/events",
     *     tags={"Events"},
     *     summary="Create a new academic event for the platform",
     *     description="Creates a new academic event that will be automatically published and made available for registration. Academic users can create conferences, workshops, seminars, lectures, and other educational events. The event will be visible to all platform users based on its visibility settings and will support registration management.",
     *     operationId="createAcademicEvent",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Complete event details for creation",
     *         @OA\JsonContent(
     *             required={"title", "description", "start_date", "end_date", "location_type", "visibility"},
     *             @OA\Property(
     *                 property="title", 
     *                 type="string", 
     *                 maxLength=255,
     *                 example="International Conference on Artificial Intelligence in Healthcare",
     *                 description="Clear, descriptive title of the academic event"
     *             ),
     *             @OA\Property(
     *                 property="description", 
     *                 type="string",
     *                 example="Join leading researchers and practitioners for a comprehensive exploration of AI applications in modern healthcare. This conference will cover machine learning in diagnostics, ethical considerations in AI-driven treatment, and future trends in medical AI research.",
     *                 description="Detailed description of the event content, objectives, and target audience"
     *             ),
     *             @OA\Property(
     *                 property="start_date", 
     *                 type="string", 
     *                 format="date-time",
     *                 example="2025-10-15T09:00:00Z",
     *                 description="Event start date and time in ISO 8601 format (UTC)"
     *             ),
     *             @OA\Property(
     *                 property="end_date", 
     *                 type="string", 
     *                 format="date-time",
     *                 example="2025-10-17T17:00:00Z",
     *                 description="Event end date and time in ISO 8601 format (UTC). Must be after start_date."
     *             ),
     *             @OA\Property(
     *                 property="timezone", 
     *                 type="string", 
     *                 maxLength=50,
     *                 example="America/New_York",
     *                 description="Event timezone (IANA timezone identifier). Defaults to UTC if not provided."
     *             ),
     *             @OA\Property(
     *                 property="location_type", 
     *                 type="string", 
     *                 enum={"physical", "virtual", "hybrid"},
     *                 example="hybrid",
     *                 description="Format of event delivery: physical (in-person only), virtual (online only), or hybrid (both)"
     *             ),
     *             @OA\Property(
     *                 property="location", 
     *                 type="string", 
     *                 maxLength=500,
     *                 example="Stanford University Medical Center, Main Auditorium, 300 Pasteur Drive, Stanford, CA 94305",
     *                 description="Physical venue address. Required for physical and hybrid events."
     *             ),
     *             @OA\Property(
     *                 property="virtual_link", 
     *                 type="string", 
     *                 format="url",
     *                 example="https://zoom.us/j/123456789",
     *                 description="Online meeting link (Zoom, Teams, etc.). Required for virtual and hybrid events."
     *             ),
     *             @OA\Property(
     *                 property="capacity", 
     *                 type="integer", 
     *                 minimum=1,
     *                 maximum=10000,
     *                 example=250,
     *                 description="Maximum number of attendees. Leave null for unlimited capacity."
     *             ),
     *             @OA\Property(
     *                 property="visibility", 
     *                 type="string", 
     *                 enum={"public", "private"},
     *                 example="public",
     *                 description="Event visibility: public (discoverable by all users) or private (invitation only)"
     *             ),
     *             @OA\Property(
     *                 property="requirements", 
     *                 type="string", 
     *                 maxLength=1000,
     *                 example="Basic knowledge of machine learning concepts recommended. Laptop required for hands-on sessions.",
     *                 description="Prerequisites or requirements for attendees"
     *             ),
     *             @OA\Property(
     *                 property="tags", 
     *                 type="array", 
     *                 @OA\Items(type="string", maxLength=50),
     *                 example={"AI", "Healthcare", "Machine Learning", "Medical Research", "Conference"},
     *                 description="Relevant tags for categorization and searchability"
     *             ),
     *             @OA\Property(
     *                 property="poster", 
     *                 type="string", 
     *                 format="binary",
     *                 description="Event poster image (JPEG, PNG, GIF). Maximum 2MB. Optional but recommended for better visibility."
     *             ),
     *             @OA\Property(
     *                 property="registration_deadline", 
     *                 type="string", 
     *                 format="date-time",
     *                 example="2025-10-10T23:59:59Z",
     *                 description="Deadline for event registration. Defaults to event start date if not provided."
     *             ),
     *             @OA\Property(
     *                 property="contact_email", 
     *                 type="string", 
     *                 format="email",
     *                 example="conference@stanford.edu",
     *                 description="Contact email for event inquiries. Defaults to organizer's email."
     *             ),
     *             @OA\Property(
     *                 property="website_url", 
     *                 type="string", 
     *                 format="url",
     *                 example="https://ai-healthcare-conference.stanford.edu",
     *                 description="Official event website for additional information"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Academic event created and published successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event created and published successfully. Your event is now live and accepting registrations!"),
     *             @OA\Property(property="data", ref="#/components/schemas/Event", description="Complete created event details"),
     *             @OA\Property(
     *                 property="event_status",
     *                 type="object",
     *                 description="Event publication and registration status",
     *                 @OA\Property(property="is_published", type="boolean", example=true),
     *                 @OA\Property(property="registration_open", type="boolean", example=true),
     *                 @OA\Property(property="event_url", type="string", example="https://academiaworld.com/events/550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="admin_notified", type="boolean", example=true, description="Whether platform administrators were notified")
     *             ),
     *             @OA\Property(
     *                 property="next_steps",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Share your event link with potential attendees", "Monitor registrations through your dashboard", "Prepare event materials and presentations", "Set up reminder notifications for registered attendees"},
     *                 description="Suggested actions for event organizers"
     *             ),
     *             @OA\Property(
     *                 property="organizer_tools",
     *                 type="object",
     *                 description="Available tools for event management",
     *                 @OA\Property(property="edit_event", type="string", example="/api/v1/events/{uuid}"),
     *                 @OA\Property(property="view_registrations", type="string", example="/api/v1/events/{uuid}/registrations"),
     *                 @OA\Property(property="send_updates", type="string", example="/api/v1/events/{uuid}/announcements")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors in event data",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 @OA\Property(
     *                     property="title", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"The title field is required.", "The title may not be greater than 255 characters."}
     *                 ),
     *                 @OA\Property(
     *                     property="start_date", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"The start date must be a date after now."}
     *                 ),
     *                 @OA\Property(
     *                     property="location", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"The location field is required when location type is physical or hybrid."}
     *                 ),
     *                 @OA\Property(
     *                     property="virtual_link", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"The virtual link must be a valid URL."}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be logged in to create events."),
     *             @OA\Property(property="error_code", type="string", example="AUTHENTICATION_REQUIRED"),
     *             @OA\Property(property="login_url", type="string", example="/api/v1/auth/login")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="User lacks permission to create events",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your account does not have permission to create events. Please verify your email or contact support."),
     *             @OA\Property(property="error_code", type="string", example="INSUFFICIENT_PERMISSIONS"),
     *             @OA\Property(
     *                 property="required_permissions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"verified_email", "active_account"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=413,
     *         description="Uploaded poster file is too large",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The poster file is too large. Maximum size is 2MB."),
     *             @OA\Property(property="error_code", type="string", example="FILE_TOO_LARGE"),
     *             @OA\Property(property="max_size", type="string", example="2MB")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error during event creation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An error occurred while creating the event. Please try again."),
     *             @OA\Property(property="error_code", type="string", example="EVENT_CREATION_FAILED"),
     *             @OA\Property(property="support_note", type="string", example="If this error persists, please contact support@academiaworld.com")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|max:50',
            'location_type' => 'required|in:physical,virtual,hybrid',
            'location' => 'required_if:location_type,physical,hybrid|nullable|string|max:500',
            'virtual_link' => 'required_if:location_type,virtual,hybrid|nullable|url',
            'capacity' => 'nullable|integer|min:1',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'visibility' => 'required|in:public,private',
            'requirements' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['host_id'] = Auth::id();
            $data['status'] = 'published'; // Auto-publish!
            $data['timezone'] = $data['timezone'] ?? 'UTC';

            // Handle poster upload
            $posterFile = null;
            
            if ($request->hasFile('poster')) {
                // Traditional file upload
                $posterFile = $request->file('poster');
            } elseif ($request->has('poster') && is_string($request->input('poster'))) {
                // Base64 upload
                $base64Data = $request->input('poster');
                if ($this->isBase64Image($base64Data)) {
                    try {
                        $posterFile = $this->createUploadedFileFromBase64($base64Data, 'poster.jpg');
                    } catch (\Exception $e) {
                        Log::warning('Failed to process base64 poster', [
                            'user_id' => Auth::id(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            if ($posterFile) {
                $fileStorageService = new FileStorageService();
                $filename = 'event_poster_' . time() . '.' . $posterFile->getClientOriginalExtension();
                $path = 'event-posters/' . $filename;
                
                $posterUrl = $fileStorageService->storeImage($posterFile, $path, [
                    'width' => 800,
                    'height' => 600,
                    'fit' => 'fill'
                ]);
                
                $data['poster'] = $posterUrl;
            }

            $event = Event::create($data);

            // Send admin notification for new event
            SendAdminNotification::dispatch($event, 'new_event');

            // Track event creation analytics
            $this->analyticsService->trackEngagement('event_creation', [
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'metadata' => [
                    'event_title' => $event->title,
                    'event_status' => $event->status,
                    'location_type' => $event->location_type,
                    'visibility' => $event->visibility,
                    'has_capacity_limit' => !is_null($event->capacity),
                ]
            ]);

            Log::info('Event created successfully', [
                'event_id' => $event->id,
                'host_id' => Auth::id(),
                'title' => $event->title
            ]);

            return response()->json([
                'message' => 'Event created and published successfully!',
                'data' => $this->transformEventData($event->load('host'))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create event',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * Retrieve detailed information about a specific academic event
     * 
     * @OA\Get(
     *     path="/api/v1/events/{event}",
     *     tags={"Events"},
     *     summary="Retrieve detailed information about a specific academic event",
     *     description="Fetches comprehensive details about a specific academic event including host information, registration status, available spots, and event resources. Only published events are accessible to the public, while event organizers can view their own unpublished events. The response includes registration statistics and user-specific information when authenticated.",
     *     operationId="getAcademicEventDetails",
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="Event UUID identifier",
     *         @OA\Schema(
     *             type="string", 
     *             format="uuid", 
     *             example="550e8400-e29b-41d4-a716-446655440000"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="include_resources",
     *         in="query",
     *         description="Include associated resources in the response",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             default=false,
     *             example=true
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="include_forums",
     *         in="query",
     *         description="Include discussion forums in the response",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             default=false,
     *             example=true
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event details retrieved successfully with comprehensive information",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete event information with registration details",
     *                 @OA\Property(property="event", ref="#/components/schemas/Event", description="Complete event details"),
     *                 @OA\Property(property="registration_count", type="integer", example=47, description="Current number of registered attendees"),
     *                 @OA\Property(property="available_spots", type="integer", nullable=true, example=153, description="Remaining registration spots (null if unlimited)"),
     *                 @OA\Property(property="is_full", type="boolean", example=false, description="Whether the event has reached capacity"),
     *                 @OA\Property(property="user_registered", type="boolean", example=true, description="Whether the current user is registered (only for authenticated users)"),
     *                 @OA\Property(
     *                     property="registration_status",
     *                     type="object",
     *                     description="Registration availability information",
     *                     @OA\Property(property="is_open", type="boolean", example=true, description="Whether registration is currently open"),
     *                     @OA\Property(property="deadline", type="string", format="date-time", nullable=true, example="2025-09-10T23:59:59Z"),
     *                     @OA\Property(property="requires_approval", type="boolean", example=false, description="Whether registrations require host approval")
     *                 ),
     *                 @OA\Property(
     *                     property="event_statistics",
     *                     type="object",
     *                     description="Event engagement statistics",
     *                     @OA\Property(property="view_count", type="integer", example=342, description="Number of times event has been viewed"),
     *                     @OA\Property(property="share_count", type="integer", example=28, description="Number of times event has been shared"),
     *                     @OA\Property(property="forum_posts", type="integer", example=15, description="Number of discussion forum posts"),
     *                     @OA\Property(property="resources_count", type="integer", example=8, description="Number of associated resources")
     *                 ),
     *                 @OA\Property(
     *                     property="similar_events",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Event"),
     *                     description="Other events with similar tags or from the same host (maximum 3)"
     *                 ),
     *                 @OA\Property(
     *                     property="accessibility_info",
     *                     type="object",
     *                     description="Accessibility and special requirements information",
     *                     @OA\Property(property="wheelchair_accessible", type="boolean", nullable=true, example=true),
     *                     @OA\Property(property="sign_language", type="boolean", nullable=true, example=false),
     *                     @OA\Property(property="live_captions", type="boolean", nullable=true, example=true),
     *                     @OA\Property(property="special_needs_contact", type="string", nullable=true, example="accessibility@university.edu")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found or not accessible to the current user",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event not found or not accessible"),
     *             @OA\Property(property="error_code", type="string", example="EVENT_NOT_FOUND"),
     *             @OA\Property(
     *                 property="possible_reasons",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Event UUID does not exist", "Event is not published yet", "Event is private and you're not invited", "Event has been cancelled or banned"}
     *             ),
     *             @OA\Property(
     *                 property="suggestions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Check the event UUID for typos", "Contact the event organizer if you believe you should have access", "Browse other available events in our catalog"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access forbidden - event is private or restricted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This event is private and requires an invitation"),
     *             @OA\Property(property="error_code", type="string", example="EVENT_ACCESS_RESTRICTED"),
     *             @OA\Property(property="contact_organizer", type="string", example="To request access, contact the event organizer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error while retrieving event details",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show(Event $event): JsonResponse
    {
        // Only show published events to public or owner's events
        if ($event->status !== 'published' && $event->host_id !== Auth::id()) {
            return response()->json([
                'message' => 'Event not found or not accessible'
            ], 404);
        }

        $event->load([
            'host:id,uuid,first_name,last_name,institution,department,position',
            'registrations' => function($query) {
                $query->wherePivot('status', 'registered')
                      ->select('users.id', 'users.uuid', 'users.first_name', 'users.last_name', 'users.institution');
            }
        ]);

        // Track event view analytics
        $this->analyticsService->trackEngagement('event_view', [
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'metadata' => [
                'event_title' => $event->title,
                'event_status' => $event->status,
                'location_type' => $event->location_type,
            ]
        ]);

        return response()->json([
            'message' => 'Event retrieved successfully',
            'data' => [
                'event' => $this->transformEventData($event),
                'registration_count' => $event->registrations->count(),
                'available_spots' => $event->available_spots,
                'is_full' => $event->is_full,
                'user_registered' => Auth::check() ?
                    $event->registrations->where('user_id', Auth::id())->isNotEmpty() : false
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/events/{event}",
     *     tags={"Events"},
     *     summary="Update an event",
     *     description="Update event details (only by event owner)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="Event UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date-time"),
     *             @OA\Property(property="end_date", type="string", format="date-time"),
     *             @OA\Property(property="location_type", type="string", enum={"physical", "virtual", "hybrid"}),
     *             @OA\Property(property="location", type="string", maxLength=500),
     *             @OA\Property(property="virtual_link", type="string", format="url"),
     *             @OA\Property(property="capacity", type="integer", minimum=1),
     *             @OA\Property(property="visibility", type="string", enum={"public", "private"}),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to edit this event",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user || !$event->canBeEditedBy($user)) {
            return response()->json([
                'message' => 'Unauthorized to edit this event'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'start_date' => 'sometimes|required|date|after:now',
            'end_date' => 'sometimes|required|date|after:start_date',
            'timezone' => 'sometimes|nullable|string|max:50',
            'location_type' => 'sometimes|required|in:physical,virtual,hybrid',
            'location' => 'sometimes|required_if:location_type,physical,hybrid|nullable|string|max:500',
            'virtual_link' => 'sometimes|required_if:location_type,virtual,hybrid|nullable|url',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'poster' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'visibility' => 'sometimes|required|in:public,private',
            'requirements' => 'sometimes|nullable|string|max:1000',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();

            // Handle poster upload
            $posterFile = null;
            
            if ($request->hasFile('poster')) {
                // Traditional file upload
                $posterFile = $request->file('poster');
            } elseif ($request->has('poster') && is_string($request->input('poster'))) {
                // Base64 upload
                $base64Data = $request->input('poster');
                if ($this->isBase64Image($base64Data)) {
                    try {
                        $posterFile = $this->createUploadedFileFromBase64($base64Data, 'poster.jpg');
                    } catch (\Exception $e) {
                        Log::warning('Failed to process base64 poster for update', [
                            'user_id' => Auth::id(),
                            'event_id' => $event->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            if ($posterFile) {
                $fileStorageService = new FileStorageService();
                
                // Delete old poster
                if ($event->poster) {
                    $fileStorageService->delete($event->poster);
                }
                
                $filename = 'event_poster_' . $event->id . '_' . time() . '.' . $posterFile->getClientOriginalExtension();
                $path = 'event-posters/' . $filename;
                
                $posterUrl = $fileStorageService->storeImage($posterFile, $path, [
                    'width' => 800,
                    'height' => 600,
                    'fit' => 'fill'
                ]);
                
                $data['poster'] = $posterUrl;
            }

            $event->update($data);
            $event->load('host:id,uuid,first_name,last_name,institution,department,position');

            return response()->json([
                'message' => 'Event updated successfully',
                'data' => $this->transformEventData($event)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update event',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/events/{event}",
     *     tags={"Events"},
     *     summary="Delete an event",
     *     description="Delete event (only by event owner)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="Event UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to delete this event",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy(Event $event): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user || !$event->canBeEditedBy($user)) {
            return response()->json([
                'message' => 'Unauthorized to delete this event'
            ], 403);
        }

        try {
            // Soft delete the event (don't delete poster - event can be restored)
            $event->delete();

            Log::info('Event soft deleted successfully', [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Event deletion failed', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete event',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * Register academic user for an event
     * 
     * @OA\Post(
     *     path="/api/v1/events/{event}/register",
     *     tags={"Events"},
     *     summary="Register academic user for an event",
     *     description="Registers the authenticated academic user for a specific event, enabling them to attend and participate in discussions. The registration process includes capacity checks, deadline validation, and automatic confirmation email sending. Users cannot register for their own events or events that have reached capacity.",
     *     operationId="registerForAcademicEvent",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="Event UUID identifier",
     *         @OA\Schema(
     *             type="string", 
     *             format="uuid", 
     *             example="550e8400-e29b-41d4-a716-446655440000"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional registration information",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="notes", 
     *                 type="string", 
     *                 maxLength=1000,
     *                 example="I'm particularly interested in the AI ethics sessions and would like to contribute to the panel discussion.",
     *                 description="Optional notes or comments about your attendance"
     *             ),
     *             @OA\Property(
     *                 property="dietary_requirements", 
     *                 type="string", 
     *                 maxLength=500,
     *                 example="Vegetarian, no nuts",
     *                 description="Special dietary requirements for catered events"
     *             ),
     *             @OA\Property(
     *                 property="accessibility_needs", 
     *                 type="string", 
     *                 maxLength=500,
     *                 example="Wheelchair access required",
     *                 description="Accessibility accommodations needed"
     *             ),
     *             @OA\Property(
     *                 property="emergency_contact", 
     *                 type="object",
     *                 description="Emergency contact information",
     *                 @OA\Property(property="name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="phone", type="string", example="+1-555-987-6543"),
     *                 @OA\Property(property="relationship", type="string", example="Colleague")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful - user is now registered for the event",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully registered for 'AI in Healthcare Conference'! Confirmation email sent."),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 description="Registration confirmation details",
     *                 @OA\Property(property="registration_uuid", type="string", format="uuid", example="abc12345-e29b-41d4-a716-446655440999", description="Unique registration identifier"),
     *                 @OA\Property(property="event_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="user_uuid", type="string", format="uuid", example="660f9400-e29b-41d4-a716-446655440001"),
     *                 @OA\Property(property="status", type="string", example="registered", description="Registration status"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Looking forward to the AI sessions!"),
     *                 @OA\Property(property="registered_at", type="string", format="date-time", example="2025-08-06T16:30:00Z"),
     *                 @OA\Property(property="registration_number", type="string", example="AI2025-0042", description="Human-readable registration number")
     *             ),
     *             @OA\Property(
     *                 property="event_info",
     *                 type="object",
     *                 description="Relevant event information for the registrant",
     *                 @OA\Property(property="title", type="string", example="AI in Healthcare Conference"),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2025-10-15T09:00:00Z"),
     *                 @OA\Property(property="location", type="string", example="Stanford Medical Center"),
     *                 @OA\Property(property="total_registered", type="integer", example=48, description="Total registrations after this one"),
     *                 @OA\Property(property="spots_remaining", type="integer", nullable=true, example=152)
     *             ),
     *             @OA\Property(
     *                 property="next_steps",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Check your email for confirmation details", "Add the event to your calendar", "Join the event discussion forum", "Download pre-event materials when available", "Contact organizer with any questions"},
     *                 description="Recommended actions after registration"
     *             ),
     *             @OA\Property(
     *                 property="important_info",
     *                 type="object",
     *                 description="Important information for attendees",
     *                 @OA\Property(property="check_in_time", type="string", example="Please arrive 30 minutes early for check-in"),
     *                 @OA\Property(property="what_to_bring", type="string", example="Laptop, notebook, business cards"),
     *                 @OA\Property(property="parking_info", type="string", example="Free parking available in Lot B"),
     *                 @OA\Property(property="cancellation_policy", type="string", example="Free cancellation up to 48 hours before the event")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Registration failed due to business rules or constraints",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration failed: Event has reached capacity"),
     *             @OA\Property(property="error_code", type="string", example="EVENT_FULL"),
     *             @OA\Property(
     *                 property="failure_reasons",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Event has reached maximum capacity", "Registration deadline has passed", "Event is not published", "You are already registered", "Event hosts cannot register for their own events"}
     *             ),
     *             @OA\Property(
     *                 property="suggestions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Join the waitlist if available", "Look for similar upcoming events", "Contact the organizer about potential additional spots", "Set up an alert for future events by this organizer"}
     *             ),
     *             @OA\Property(
     *                 property="alternative_actions",
     *                 type="object",
     *                 description="Alternative actions user can take",
     *                 @OA\Property(property="waitlist_available", type="boolean", example=true),
     *                 @OA\Property(property="similar_events", type="string", example="/api/v1/events?search=AI%20healthcare"),
     *                 @OA\Property(property="contact_organizer", type="string", example="conference@stanford.edu")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be logged in to register for events"),
     *             @OA\Property(property="error_code", type="string", example="AUTHENTICATION_REQUIRED"),
     *             @OA\Property(property="login_url", type="string", example="/api/v1/auth/login")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="User lacks permission to register for this event",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your account does not have permission to register for events"),
     *             @OA\Property(property="error_code", type="string", example="INSUFFICIENT_PERMISSIONS"),
     *             @OA\Property(
     *                 property="required_conditions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Email verification required", "Active account status", "Institution affiliation verified"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found or not available for registration",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event not found or not available for registration"),
     *             @OA\Property(property="error_code", type="string", example="EVENT_NOT_FOUND"),
     *             @OA\Property(
     *                 property="possible_reasons",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Event UUID does not exist", "Event has been cancelled", "Event is private and you're not invited", "Event has been deleted by organizer"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - user is already registered for this event",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are already registered for this event"),
     *             @OA\Property(property="error_code", type="string", example="ALREADY_REGISTERED"),
     *             @OA\Property(
     *                 property="existing_registration",
     *                 type="object",
     *                 description="Details of existing registration",
     *                 @OA\Property(property="registered_at", type="string", format="date-time", example="2025-08-01T10:30:00Z"),
     *                 @OA\Property(property="registration_number", type="string", example="AI2025-0031"),
     *                 @OA\Property(property="modify_registration", type="string", example="/api/v1/events/550e8400-e29b-41d4-a716-446655440000/registration")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors in registration data",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 @OA\Property(
     *                     property="notes", 
     *                     type="array", 
     *                     @OA\Items(type="string"),
     *                     example={"The notes may not be greater than 1000 characters."}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error during registration process",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration failed due to server error. Please try again."),
     *             @OA\Property(property="error_code", type="string", example="REGISTRATION_ERROR"),
     *             @OA\Property(property="support_note", type="string", example="If this error persists, please contact support@academiaworld.com")
     *         )
     *     )
     * )
     */
    public function register(Request $request, Event $event): JsonResponse
    {
        if ($event->status !== 'published') {
            return response()->json([
                'message' => 'Event is not available for registration'
            ], 400);
        }

        if ($event->host_id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot register for your own event'
            ], 400);
        }

        if ($event->is_full) {
            return response()->json([
                'message' => 'Event is full'
            ], 400);
        }

        // Check if already registered
        $existingRegistration = $event->registrations()
            ->where('user_id', Auth::id())
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'message' => 'You are already registered for this event'
            ], 400);
        }

        try {
            $event->registrations()->attach(Auth::id(), [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now(),
                'notes' => $request->get('notes')
            ]);

            $user = Auth::user();
            
            // Ensure user is authenticated
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Send confirmation email to user
            Mail::to($user->email)->send(
                new EventRegistrationConfirmation($user, $event)
            );

            // Send notification to admins
            SendAdminNotification::dispatch($event, 'new_registration', $user);

            // Track event registration analytics
            $this->analyticsService->trackEngagement('event_registration', [
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'metadata' => [
                    'event_title' => $event->title,
                    'event_status' => $event->status,
                    'location_type' => $event->location_type,
                    'registration_status' => 'registered',
                ]
            ]);

            Log::info('User registered for event', [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'event_title' => $event->title
            ]);

            return response()->json([
                'message' => 'Successfully registered for the event!'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/events/{event}/unregister",
     *     tags={"Events"},
     *     summary="Unregister from an event",
     *     description="Remove user registration from a specific event",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="Event UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unregistration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully unregistered from event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Not registered for this event",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function unregister(Event $event): JsonResponse
    {
        $registration = $event->registrations()
            ->where('user_id', Auth::id())
            ->wherePivot('status', 'registered')
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'You are not registered for this event'
            ], 400);
        }

        try {
            // Update registration status to cancelled instead of deleting
            $event->registrations()->updateExistingPivot(Auth::id(), [
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);

            Log::info('User cancelled event registration', [
                'user_id' => Auth::id(),
                'event_id' => $event->id,
                'event_title' => $event->title
            ]);

            // Track event unregistration analytics
            $this->analyticsService->trackEngagement('event_unregistration', [
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'metadata' => [
                    'event_title' => $event->title,
                    'event_status' => $event->status,
                    'location_type' => $event->location_type,
                    'registration_status' => 'cancelled',
                ]
            ]);

            return response()->json([
                'message' => 'Successfully cancelled your registration for the event'
            ]);

        } catch (\Exception $e) {
            Log::error('Registration cancellation failed', [
                'user_id' => Auth::id(),
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Registration cancellation failed',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/my-events",
     *     tags={"Events"},
     *     summary="Get user's hosted events",
     *     description="Retrieve all events hosted by the authenticated user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Event")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function myEvents(Request $request): JsonResponse
    {
        $events = Event::where('host_id', Auth::id())
            ->with(['registrations' => function($query) {
                $query->wherePivot('status', 'registered');
            }])
            ->orderBy('start_date', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Your events retrieved successfully',
            'data' => collect($events->items())->map(function($event) {
                return $this->transformEventData($event);
            }),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/my-registrations",
     *     tags={"Events"},
     *     summary="Get user's event registrations",
     *     description="Retrieve all events the authenticated user has registered for",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User registrations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventRegistration")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function myRegistrations(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $events = $user->registeredEvents()
            ->wherePivot('status', 'registered')
            ->with(['host:id,uuid,first_name,last_name,institution'])
            ->orderBy('start_date', 'asc')
            ->paginate(10);

        return response()->json([
            'message' => 'Your registrations retrieved successfully',
            'data' => collect($events->items())->map(function($event) {
                return $this->transformEventData($event);
            }),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/my-cancelled-registrations",
     *     tags={"Events"},
     *     summary="Get user's cancelled event registrations",
     *     description="Retrieve all events the authenticated user has cancelled registration for",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User cancelled registrations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="event", ref="#/components/schemas/Event"),
     *                     @OA\Property(property="registration_details", type="object",
     *                         @OA\Property(property="status", type="string", example="cancelled"),
     *                         @OA\Property(property="registered_at", type="string", format="date-time"),
     *                         @OA\Property(property="cancelled_at", type="string", format="date-time"),
     *                         @OA\Property(property="notes", type="string", nullable=true)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function myCancelledRegistrations(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $events = $user->registeredEvents()
            ->wherePivot('status', 'cancelled')
            ->with(['host:id,uuid,first_name,last_name,institution'])
            ->orderBy('event_registrations.cancelled_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Your cancelled registrations retrieved successfully',
            'data' => collect($events->items())->map(function($event) {
                return [
                    'event' => $this->transformEventData($event),
                    'registration_details' => [
                        'status' => $event->pivot->status,
                        'registered_at' => $event->pivot->registered_at,
                        'cancelled_at' => $event->pivot->cancelled_at,
                        'notes' => $event->pivot->notes
                    ]
                ];
            }),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/events/{event}/attendees",
     *     tags={"Events"},
     *     summary="Get event attendees",
     *     description="Retrieve list of event attendees (only for event host)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="Event UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendees retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="total_count", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to view attendees",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function attendees(Event $event): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user || !$event->canBeEditedBy($user)) {
            return response()->json([
                'message' => 'Unauthorized to view attendees'
            ], 403);
        }

        $attendees = $event->registrations()
            ->wherePivot('status', 'registered')
            ->select(['users.uuid', 'first_name', 'last_name', 'email', 'institution', 'position'])
            ->get();

        return response()->json([
            'message' => 'Attendees retrieved successfully',
            'data' => $attendees->map(function($attendee) {
                return [
                    'uuid' => $attendee->uuid,
                    'first_name' => $attendee->first_name,
                    'last_name' => $attendee->last_name,
                    'full_name' => $attendee->first_name . ' ' . $attendee->last_name,
                    'email' => $attendee->email,
                    'institution' => $attendee->institution,
                    'position' => $attendee->position,
                ];
            }),
            'total_count' => $attendees->count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/events/search",
     *     tags={"Events"},
     *     summary="Advanced event search",
     *     description="Search events with advanced filtering options",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query for title, description, and tags",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by event category",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Conference", "Workshop", "Seminar", "Research", "Networking"})
     *     ),
     *     @OA\Parameter(
     *         name="location_type",
     *         in="query",
     *         description="Filter by location type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"physical", "virtual", "hybrid"})
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter events starting from this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter events ending before this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="Filter by specific tags (comma-separated)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="capacity_min",
     *         in="query",
     *         description="Minimum event capacity",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="capacity_max",
     *         in="query",
     *         description="Maximum event capacity",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="availability",
     *         in="query",
     *         description="Filter by availability",
     *         required=false,
     *         @OA\Schema(type="string", enum={"available", "full", "any"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort results by field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"date", "popularity", "capacity", "created_at"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page (max 50)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Event")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(
     *                 property="facets",
     *                 type="object",
     *                 @OA\Property(property="total_count", type="integer"),
     *                 @OA\Property(property="location_types", type="object"),
     *                 @OA\Property(property="popular_tags", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|in:Conference,Workshop,Seminar,Research,Networking',
            'location_type' => 'sometimes|string|in:physical,virtual,hybrid',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'tags' => 'sometimes|string',
            'capacity_min' => 'sometimes|integer|min:1',
            'capacity_max' => 'sometimes|integer|min:1|gte:capacity_min',
            'availability' => 'sometimes|string|in:available,full,any',
            'sort_by' => 'sometimes|string|in:date,popularity,capacity,created_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Event::with(['host:id,uuid,first_name,last_name,institution'])
            ->published()
            ->public()
            ->upcoming();

        // Text search
        if ($request->has('q')) {
            $searchTerm = $request->get('q');
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('location', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('tags', $searchTerm);
            });
        }

        // Category filter (check tags for category)
        if ($request->has('category')) {
            $query->whereJsonContains('tags', $request->get('category'));
        }

        // Location type filter
        if ($request->has('location_type')) {
            $query->where('location_type', $request->get('location_type'));
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('start_date', '<=', $request->get('date_to'));
        }

        // Tags filter
        if ($request->has('tags')) {
            $tags = explode(',', $request->get('tags'));
            $tags = array_map('trim', $tags);
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Capacity filters
        if ($request->has('capacity_min')) {
            $query->where('capacity', '>=', $request->get('capacity_min'));
        }

        if ($request->has('capacity_max')) {
            $query->where('capacity', '<=', $request->get('capacity_max'));
        }

        // Availability filter
        if ($request->has('availability') && $request->get('availability') !== 'any') {
            $query->withCount(['registrations' => function($q) {
                $q->wherePivot('status', 'registered');
            }]);

            if ($request->get('availability') === 'available') {
                $query->havingRaw('(capacity IS NULL OR registrations_count < capacity)');
            } elseif ($request->get('availability') === 'full') {
                $query->havingRaw('(capacity IS NOT NULL AND registrations_count >= capacity)');
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->get('sort_order', 'asc');

        switch ($sortBy) {
            case 'popularity':
                $query->withCount('registrations')
                      ->orderBy('registrations_count', $sortOrder);
                break;
            case 'capacity':
                $query->orderBy('capacity', $sortOrder);
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'date':
            default:
                $query->orderBy('start_date', $sortOrder);
                break;
        }

        // Secondary sort by creation date
        if ($sortBy !== 'created_at') {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 50);
        $events = $query->paginate($perPage);

        // Generate facets for frontend filtering
        $facets = [
            'total_count' => $events->total(),
            'location_types' => Event::published()->public()->upcoming()
                ->selectRaw('location_type, COUNT(*) as count')
                ->groupBy('location_type')
                ->pluck('count', 'location_type'),
            'popular_tags' => Event::published()->public()->upcoming()
                ->whereNotNull('tags')
                ->get()
                ->pluck('tags')
                ->flatten()
                ->countBy()
                ->sortDesc()
                ->take(10)
                ->keys()
        ];

        return response()->json([
            'message' => 'Search results retrieved successfully',
            'data' => collect($events->items())->map(function($event) {
                return $this->transformEventData($event);
            }),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'facets' => $facets
        ]);
    }

    /**
     * Transform event data to exclude sensitive fields like id
     */
    private function transformEventData($event): array
    {
        $data = [
            'uuid' => $event->uuid,
            'title' => $event->title,
            'description' => $event->description,
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'timezone' => $event->timezone,
            'location_type' => $event->location_type,
            'location' => $event->location,
            'virtual_link' => $event->virtual_link,
            'capacity' => $event->capacity,
            'poster' => $event->poster ? Storage::url($event->poster) : null,
            'agenda' => $event->agenda,
            'tags' => $event->tags,
            'status' => $event->status,
            'visibility' => $event->visibility,
            'requirements' => $event->requirements,
            'created_at' => $event->created_at,
            'updated_at' => $event->updated_at,
        ];

        // Include host data if loaded, but exclude host ID
        if ($event->relationLoaded('host') && $event->host) {
            $data['host'] = [
                'uuid' => $event->host->uuid,
                'first_name' => $event->host->first_name,
                'last_name' => $event->host->last_name,
                'full_name' => $event->host->full_name,
                'institution' => $event->host->institution,
                'department' => $event->host->department,
                'position' => $event->host->position,
            ];
        }

        return $data;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/events/{event}/poster",
     *     tags={"Events"},
     *     summary="Upload event poster",
     *     description="Upload a poster for an event (host only)",
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
     *                 @OA\Property(property="poster", type="string", format="binary", description="Poster image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Poster uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="poster_url", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function uploadPoster(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        
        // Check permissions - only event host or admin can upload poster
        if (!$user || ($user->id !== $event->host_id && !$user->isAdmin())) {
            return response()->json([
                'message' => 'You are not authorized to upload poster for this event'
            ], 403);
        }
        
        // Custom validation for both file upload and base64
        $validator = Validator::make($request->all(), [
            'poster' => 'required'
        ]);
        
        // Add custom validation for image format
        $validator->after(function ($validator) use ($request) {
            $poster = $request->input('poster');
            
            if ($request->hasFile('poster')) {
                // Validate uploaded file
                $file = $request->file('poster');
                if (!$file->isValid()) {
                    $validator->errors()->add('poster', 'The uploaded file is not valid.');
                    return;
                }
                
                if (!in_array($file->getClientOriginalExtension(), ['jpeg', 'jpg', 'png', 'gif'])) {
                    $validator->errors()->add('poster', 'The poster must be a file of type: jpeg, jpg, png, gif.');
                    return;
                }
                
                if ($file->getSize() > 2048 * 1024) { // 2MB in bytes
                    $validator->errors()->add('poster', 'The poster may not be greater than 2048 kilobytes.');
                    return;
                }
                
                // Check if it's actually an image
                $imageInfo = getimagesize($file->getPathname());
                if (!$imageInfo) {
                    $validator->errors()->add('poster', 'The poster must be an image.');
                }
            } elseif (is_string($poster)) {
                // Validate base64 string
                if (!$this->isBase64Image($poster)) {
                    $validator->errors()->add('poster', 'The poster must be a valid base64 encoded image.');
                    return;
                }
                
                // Check base64 size (approximate)
                $base64Size = (strlen($poster) * 3 / 4) - substr_count($poster, '=');
                if ($base64Size > 2048 * 1024) { // 2MB
                    $validator->errors()->add('poster', 'The poster may not be greater than 2048 kilobytes.');
                }
            } else {
                $validator->errors()->add('poster', 'The poster must be a valid image file or base64 encoded image.');
            }
        });
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Handle both multipart/form-data and JSON with base64
            $posterFile = null;
            
            if ($request->hasFile('poster')) {
                $posterFile = $request->file('poster');
            } elseif ($request->has('poster') && is_string($request->input('poster'))) {
                $base64Data = $request->input('poster');
                if ($this->isBase64Image($base64Data)) {
                    try {
                        $posterFile = $this->createUploadedFileFromBase64($base64Data, 'poster.jpg');
                    } catch (\Exception $e) {
                        Log::warning('Failed to process base64 poster', [
                            'user_id' => Auth::id(),
                            'event_id' => $event->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            if (!$posterFile) {
                return response()->json([
                    'message' => 'Validation errors',
                    'errors' => [
                        'poster' => ['The poster field is required and must be a valid image file or base64 encoded image.']
                    ]
                ], 422);
            }
            
            // Delete old poster if exists
            if ($event->poster) {
                $fileStorageService = new FileStorageService();
                $fileStorageService->delete($event->poster);
            }
            
            // Upload new poster
            $fileStorageService = new FileStorageService();
            $filename = 'event_poster_' . $event->id . '_' . time() . '.' . $posterFile->getClientOriginalExtension();
            $path = 'event-posters/' . $filename;
            
            $posterUrl = $fileStorageService->storeImage($posterFile, $path, [
                'width' => 800,
                'height' => 600,
                'quality' => 90
            ]);
            
            // Update event with new poster URL
            $event->update(['poster' => $posterUrl]);
            
            Log::info('Event poster uploaded successfully', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'poster_url' => $posterUrl
            ]);
            
            return response()->json([
                'message' => 'Poster uploaded successfully',
                'data' => [
                    'poster_url' => $posterUrl
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Event poster upload failed', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to upload poster',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/v1/events/{event}/poster",
     *     tags={"Events"},
     *     summary="Delete event poster",
     *     description="Remove the poster from an event (host only)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Poster deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized"),
     *     @OA\Response(response=404, description="No poster found")
     * )
     */
    public function deletePoster(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        
        // Check permissions - only event host or admin can delete poster
        if (!$user || ($user->id !== $event->host_id && !$user->isAdmin())) {
            return response()->json([
                'message' => 'You are not authorized to delete poster for this event'
            ], 403);
        }
        
        if (!$event->poster) {
            return response()->json([
                'message' => 'No poster found for this event'
            ], 404);
        }
        
        try {
            // Delete poster file from storage
            $fileStorageService = new FileStorageService();
            $fileStorageService->delete($event->poster);
            
            // Remove poster URL from event
            $event->update(['poster' => null]);
            
            Log::info('Event poster deleted successfully', [
                'event_id' => $event->id,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Poster deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Event poster deletion failed', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete poster',
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
}
