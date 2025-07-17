<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Events",
 *     description="Event management operations"
 * )
 */
class EventController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/events",
     *     tags={"Events"},
     *     summary="Browse published events",
     *     description="Get a paginated list of published public events with filtering options",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in title, description, and tags",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *     @OA\Response(
     *         response=200,
     *         description="Events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Event")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with(['host:id,first_name,last_name,institution'])
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
            'data' => $events->items(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/events",
     *     tags={"Events"},
     *     summary="Create a new event",
     *     description="Create a new event (automatically published for moderation)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "start_date", "end_date", "location_type"},
     *             @OA\Property(property="title", type="string", maxLength=255, example="AI in Academic Research"),
     *             @OA\Property(property="description", type="string", example="Workshop on implementing AI tools in academic research"),
     *             @OA\Property(property="start_date", type="string", format="date-time", example="2025-08-15 14:00:00"),
     *             @OA\Property(property="end_date", type="string", format="date-time", example="2025-08-15 17:00:00"),
     *             @OA\Property(property="timezone", type="string", maxLength=50, example="UTC"),
     *             @OA\Property(property="location_type", type="string", enum={"physical", "virtual", "hybrid"}, example="hybrid"),
     *             @OA\Property(property="location", type="string", maxLength=500, example="University Main Hall"),
     *             @OA\Property(property="virtual_link", type="string", format="url", example="https://zoom.us/j/123456789"),
     *             @OA\Property(property="capacity", type="integer", minimum=1, example=50),
     *             @OA\Property(property="visibility", type="string", enum={"public", "private"}, example="public"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"AI", "Research", "Workshop"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event created and published successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
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
            $data = $request->validated();
            $data['host_id'] = Auth::id();
            $data['status'] = 'published'; // Auto-publish!
            $data['timezone'] = $data['timezone'] ?? 'UTC';

            // Handle poster upload
            if ($request->hasFile('poster')) {
                $posterPath = $request->file('poster')->store('event-posters', 'public');
                $data['poster'] = $posterPath;
            }

            $event = Event::create($data);

            return response()->json([
                'message' => 'Event created and published successfully!',
                'data' => $event->load('host:id,first_name,last_name,institution')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create event',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/events/{id}",
     *     tags={"Events"},
     *     summary="Get event details",
     *     description="Retrieve detailed information about a specific event",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found or not accessible",
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
            'host:id,first_name,last_name,institution,department,position',
            'registrations' => function($query) {
                $query->where('status', 'registered')
                      ->with('user:id,first_name,last_name,institution');
            }
        ]);

        return response()->json([
            'message' => 'Event retrieved successfully',
            'data' => [
                'event' => $event,
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
     *     path="/api/v1/events/{id}",
     *     tags={"Events"},
     *     summary="Update an event",
     *     description="Update event details (only by event owner)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
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
        if (!$event->canBeEditedBy(Auth::user())) {
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
            if ($request->hasFile('poster')) {
                // Delete old poster
                if ($event->poster) {
                    Storage::disk('public')->delete($event->poster);
                }
                $data['poster'] = $request->file('poster')->store('event-posters', 'public');
            }

            $event->update($data);

            return response()->json([
                'message' => 'Event updated successfully',
                'data' => $event->fresh(['host:id,first_name,last_name,institution'])
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
     *     path="/api/v1/events/{id}",
     *     tags={"Events"},
     *     summary="Delete an event",
     *     description="Delete event (only by event owner)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
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
        if (!$event->canBeEditedBy(Auth::user())) {
            return response()->json([
                'message' => 'Unauthorized to delete this event'
            ], 403);
        }

        try {
            // Delete poster if exists
            if ($event->poster) {
                Storage::disk('public')->delete($event->poster);
            }

            $event->delete();

            return response()->json([
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete event',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/events/{id}/register",
     *     tags={"Events"},
     *     summary="Register for an event",
     *     description="Register the authenticated user for a specific event",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Looking forward to this workshop!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully registered for event"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="event_id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="registered_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Registration failed",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
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
                'status' => 'registered',
                'registered_at' => now(),
                'notes' => $request->get('notes')
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
     *     path="/api/v1/events/{id}/unregister",
     *     tags={"Events"},
     *     summary="Unregister from an event",
     *     description="Remove user registration from a specific event",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
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
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'You are not registered for this event'
            ], 400);
        }

        try {
            $event->registrations()->detach(Auth::id());

            return response()->json([
                'message' => 'Successfully unregistered from the event'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unregistration failed',
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
                $query->where('status', 'registered');
            }])
            ->orderBy('start_date', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Your events retrieved successfully',
            'data' => $events->items(),
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
        $events = $user->registeredEvents()
            ->wherePivot('status', 'registered')
            ->with(['host:id,first_name,last_name,institution'])
            ->orderBy('start_date', 'asc')
            ->paginate(10);

        return response()->json([
            'message' => 'Your registrations retrieved successfully',
            'data' => $events->items(),
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
     *     path="/api/v1/events/{id}/attendees",
     *     tags={"Events"},
     *     summary="Get event attendees",
     *     description="Retrieve list of event attendees (only for event host)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
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
        if (!$event->canBeEditedBy(Auth::user())) {
            return response()->json([
                'message' => 'Unauthorized to view attendees'
            ], 403);
        }

        $attendees = $event->registrations()
            ->wherePivot('status', 'registered')
            ->with(['user:id,first_name,last_name,email,institution,position'])
            ->get();

        return response()->json([
            'message' => 'Attendees retrieved successfully',
            'data' => $attendees,
            'total_count' => $attendees->count()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/events/{id}/ban",
     *     tags={"Admin"},
     *     summary="Ban an event (Admin only)",
     *     description="Ban an event with reason (admin access required)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Inappropriate content detected")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event banned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event banned successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Admin access required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function banEvent(Request $request, Event $event): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $event->update([
            'status' => 'banned',
            'ban_reason' => $request->reason,
            'banned_at' => now(),
            'banned_by' => Auth::id()
        ]);

        Log::channel('events')->warning('Event banned by admin', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'admin_id' => Auth::id(),
            'reason' => $request->reason
        ]);

        return response()->json([
            'message' => 'Event banned successfully',
            'data' => $event->fresh()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/events/{id}/unban",
     *     tags={"Admin"},
     *     summary="Unban an event (Admin only)",
     *     description="Restore a banned event to published status (admin access required)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event unbanned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event unbanned successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Admin access required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function unbanEvent(Event $event): JsonResponse
    {
        $event->update([
            'status' => 'published',
            'ban_reason' => null,
            'banned_at' => null,
            'banned_by' => null
        ]);

        Log::channel('events')->info('Event unbanned by admin', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'admin_id' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Event unbanned successfully',
            'data' => $event->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/events/{id}/force-delete",
     *     tags={"Admin"},
     *     summary="Force delete an event (Admin only)",
     *     description="Permanently delete an event and all associated data (admin access required)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event permanently deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Admin access required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function forceDelete(Event $event): JsonResponse
    {
        $eventTitle = $event->title;
        $eventId = $event->id;

        // Delete all registrations first
        $event->registrations()->detach();
        
        // Delete the event
        $event->delete();

        Log::channel('events')->warning('Event force deleted by admin', [
            'event_id' => $eventId,
            'event_title' => $eventTitle,
            'admin_id' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Event permanently deleted'
        ]);
    }
}
