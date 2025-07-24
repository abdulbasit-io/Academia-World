<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DiscussionForum;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Forums",
 *     description="Discussion forum management operations"
 * )
 */
class ForumController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/events/{event}/forums",
     *     tags={"Forums"},
     *     summary="Get event forums",
     *     description="Retrieve all active discussion forums for a specific event",
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
     *         description="Forums retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forums retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="type", type="string", enum={"general", "q_and_a", "networking", "feedback", "technical"}),
     *                     @OA\Property(property="post_count", type="integer"),
     *                     @OA\Property(property="participant_count", type="integer"),
     *                     @OA\Property(property="last_activity_at", type="string", format="datetime"),
     *                     @OA\Property(property="creator", type="object",
     *                         @OA\Property(property="uuid", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string")
     *                     ),
     *                     @OA\Property(property="latest_post", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     * Get all forums for an event
     */
    public function index(Event $event): JsonResponse
    {
        $forums = $event->forums()
            ->with(['creator:id,name', 'latestPost.user:id,name'])
            ->active()
            ->orderBy('type')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Forums retrieved successfully',
            'data' => $forums->map(function ($forum) {
                return [
                    'uuid' => $forum->uuid,
                    'title' => $forum->title,
                    'description' => $forum->description,
                    'type' => $forum->type,
                    'is_moderated' => $forum->is_moderated,
                    'post_count' => $forum->post_count,
                    'participant_count' => $forum->participant_count,
                    'last_activity_at' => $forum->last_activity_at,
                    'creator' => $forum->creator ? [
                        'name' => $forum->creator->name
                    ] : null,
                    'latest_post' => $forum->latestPost->first() ? [
                        'user' => $forum->latestPost->first()->user->name,
                        'created_at' => $forum->latestPost->first()->created_at,
                    ] : null,
                    'created_at' => $forum->created_at,
                ];
            })
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/events/{event}/forums",
     *     tags={"Forums"},
     *     summary="Create a new forum",
     *     description="Create a new discussion forum for an event",
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
     *         @OA\JsonContent(
     *             required={"title", "type"},
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="type", type="string", enum={"general", "q_and_a", "networking", "feedback", "technical"}),
     *             @OA\Property(property="is_moderated", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Forum created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Only event hosts can create forums")
     * )
     * Create a new forum for an event
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        // Check if user can create forums (event host or admin)
        if (!$request->user()->is_admin && $request->user()->id !== $event->host_id) {
            return response()->json([
                'message' => 'You are not authorized to create forums for this event'
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['general', 'q_and_a', 'networking', 'feedback', 'technical'])],
            'is_moderated' => ['boolean']
        ]);

        $forum = DiscussionForum::create([
            'event_id' => $event->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'is_moderated' => $validated['is_moderated'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Forum created successfully',
            'data' => [
                'uuid' => $forum->uuid,
                'title' => $forum->title,
                'description' => $forum->description,
                'type' => $forum->type,
                'is_moderated' => $forum->is_moderated,
                'post_count' => $forum->post_count,
                'participant_count' => $forum->participant_count,
                'created_at' => $forum->created_at,
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/forums/{forum}",
     *     summary="Get a specific forum",
     *     description="Retrieves a specific forum by UUID with creator and event details",
     *     operationId="getForumByUuid",
     *     tags={"Forums"},
     *     @OA\Parameter(
     *         name="forum",
     *         in="path",
     *         description="Forum UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="type", type="string", enum={"general", "qa", "academic", "feedback"}),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="creator", type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="event", type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="title", type="string")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Forum not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function show(DiscussionForum $forum): JsonResponse
    {
        $forum->load(['creator:id,name', 'event:id,title']);

        return response()->json([
            'message' => 'Forum retrieved successfully',
            'data' => [
                'uuid' => $forum->uuid,
                'title' => $forum->title,
                'description' => $forum->description,
                'type' => $forum->type,
                'is_active' => $forum->is_active,
                'is_moderated' => $forum->is_moderated,
                'post_count' => $forum->post_count,
                'participant_count' => $forum->participant_count,
                'last_activity_at' => $forum->last_activity_at,
                'event' => [
                    'title' => $forum->event->title
                ],
                'creator' => [
                    'name' => $forum->creator->name
                ],
                'created_at' => $forum->created_at,
                'updated_at' => $forum->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/forums/{forum}",
     *     summary="Update a forum",
     *     description="Updates a forum. Only event hosts and admins can update forums.",
     *     operationId="updateForum",
     *     tags={"Forums"},
     *     @OA\Parameter(
     *         name="forum",
     *         in="path",
     *         description="Forum UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="Updated Academic Discussion"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Updated discussion forum for event"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="is_moderated", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="is_moderated", type="boolean"),
     *                 @OA\Property(property="post_count", type="integer"),
     *                 @OA\Property(property="participant_count", type="integer"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to update forum",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to update this forum")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Forum not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function update(Request $request, DiscussionForum $forum): JsonResponse
    {
        // Check if user can update forums (event host or admin)
        if (!$request->user()->is_admin && $request->user()->id !== $forum->event->host_id) {
            return response()->json([
                'message' => 'You are not authorized to update this forum'
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'is_moderated' => ['boolean']
        ]);

        $forum->update($validated);

        return response()->json([
            'message' => 'Forum updated successfully',
            'data' => [
                'uuid' => $forum->uuid,
                'title' => $forum->title,
                'description' => $forum->description,
                'type' => $forum->type,
                'is_active' => $forum->is_active,
                'is_moderated' => $forum->is_moderated,
                'post_count' => $forum->post_count,
                'participant_count' => $forum->participant_count,
                'updated_at' => $forum->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/forums/{forum}",
     *     summary="Delete a forum",
     *     description="Deletes a forum and all its posts. Only event hosts and admins can delete forums.",
     *     operationId="deleteForum",
     *     tags={"Forums"},
     *     @OA\Parameter(
     *         name="forum",
     *         in="path",
     *         description="Forum UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to delete forum",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to delete this forum")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Forum not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function destroy(Request $request, DiscussionForum $forum): JsonResponse
    {
        // Check if user can delete forums (event host or admin)
        if (!$request->user()->is_admin && $request->user()->id !== $forum->event->host_id) {
            return response()->json([
                'message' => 'You are not authorized to delete this forum'
            ], 403);
        }

        $forum->delete();

        return response()->json([
            'message' => 'Forum deleted successfully'
        ]);
    }
}
