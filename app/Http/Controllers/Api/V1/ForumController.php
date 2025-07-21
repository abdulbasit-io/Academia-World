<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiscussionForum;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ForumController extends Controller
{
    /**
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
     * Get a specific forum
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
     * Update a forum
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
     * Delete a forum
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
