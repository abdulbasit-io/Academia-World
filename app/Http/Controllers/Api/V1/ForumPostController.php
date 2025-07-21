<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ForumPostController extends Controller
{
    /**
     * Get all posts in a forum
     */
    public function index(DiscussionForum $forum): JsonResponse
    {
        $posts = $forum->topLevelPosts()
            ->with(['user:id,name', 'replies.user:id,name'])
            ->withCount('replies')
            ->paginate(20);

        return response()->json([
            'message' => 'Posts retrieved successfully',
            'data' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ]
        ]);
    }

    /**
     * Create a new post in a forum
     */
    public function store(Request $request, DiscussionForum $forum): JsonResponse
    {
        // Check if user can post in this forum
        if (!$forum->canUserPost($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to post in this forum'
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:10000'],
            'parent_id' => ['nullable', 'exists:forum_posts,id']
        ]);

        // If parent_id is provided, ensure it belongs to this forum
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            $parentPost = ForumPost::find($validated['parent_id']);
            if (!$parentPost || $parentPost->forum_id !== $forum->id) {
                return response()->json([
                    'message' => 'Invalid parent post'
                ], 422);
            }
        }

        $post = ForumPost::create([
            'forum_id' => $forum->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'],
        ]);

        $post->load(['user:id,name']);

        return response()->json([
            'message' => 'Post created successfully',
            'data' => [
                'uuid' => $post->uuid,
                'content' => $post->content,
                'is_pinned' => $post->is_pinned,
                'is_solution' => $post->is_solution,
                'likes_count' => $post->likes_count,
                'replies_count' => $post->replies_count,
                'user' => [
                    'name' => $post->user->name
                ],
                'created_at' => $post->created_at,
            ]
        ], 201);
    }

    /**
     * Get a specific post with its replies
     */
    public function show(ForumPost $post): JsonResponse
    {
        $post->load([
            'user:id,name',
            'replies.user:id,name',
            'forum:id,title,type'
        ]);

        return response()->json([
            'message' => 'Post retrieved successfully',
            'data' => [
                'uuid' => $post->uuid,
                'content' => $post->content,
                'is_pinned' => $post->is_pinned,
                'is_solution' => $post->is_solution,
                'likes_count' => $post->likes_count,
                'replies_count' => $post->replies_count,
                'user' => [
                    'name' => $post->user->name
                ],
                'replies' => $post->replies->map(function ($reply) {
                    return [
                        'uuid' => $reply->uuid,
                        'content' => $reply->content,
                        'is_solution' => $reply->is_solution,
                        'likes_count' => $reply->likes_count,
                        'user' => [
                            'name' => $reply->user->name
                        ],
                        'created_at' => $reply->created_at,
                    ];
                }),
                'forum' => [
                    'title' => $post->forum->title,
                    'type' => $post->forum->type,
                ],
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ]
        ]);
    }

    /**
     * Update a post
     */
    public function update(Request $request, ForumPost $post): JsonResponse
    {
        // Check if user can edit this post
        if (!$post->canBeEditedBy($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to edit this post'
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:10000'],
        ]);

        $post->update([
            'content' => $validated['content'],
            'edited_at' => now(),
            'edited_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Post updated successfully',
            'data' => [
                'uuid' => $post->uuid,
                'content' => $post->content,
                'edited_at' => $post->edited_at,
                'updated_at' => $post->updated_at,
            ]
        ]);
    }

    /**
     * Delete a post
     */
    public function destroy(Request $request, ForumPost $post): JsonResponse
    {
        // Check if user can delete this post
        if (!$post->canBeDeletedBy($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to delete this post'
            ], 403);
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully'
        ]);
    }

    /**
     * Toggle like on a post
     */
    public function toggleLike(Request $request, ForumPost $post): JsonResponse
    {
        $result = $post->toggleLike($request->user());

        return response()->json([
            'message' => $result['liked'] ? 'Post liked' : 'Post unliked',
            'data' => $result
        ]);
    }

    /**
     * Pin/unpin a post (moderators only)
     */
    public function togglePin(Request $request, ForumPost $post): JsonResponse
    {
        // Check if user can pin posts (event host or admin)
        if (!$request->user()->is_admin && $request->user()->id !== $post->forum->event->host_id) {
            return response()->json([
                'message' => 'You are not authorized to pin posts'
            ], 403);
        }

        $post->update(['is_pinned' => !$post->is_pinned]);

        return response()->json([
            'message' => $post->is_pinned ? 'Post pinned' : 'Post unpinned',
            'data' => [
                'is_pinned' => $post->is_pinned,
            ]
        ]);
    }

    /**
     * Mark post as solution (for Q&A forums)
     */
    public function markAsSolution(Request $request, ForumPost $post): JsonResponse
    {
        // Check if this is a Q&A forum
        if ($post->forum->type !== 'q_and_a') {
            return response()->json([
                'message' => 'Solutions can only be marked in Q&A forums'
            ], 422);
        }

        // Check if user can mark solutions (post author, event host, or admin)
        $canMarkSolution = $request->user()->is_admin || 
                          $request->user()->id === $post->forum->event->host_id ||
                          ($post->parent_id && $request->user()->id === $post->parent->user_id);

        if (!$canMarkSolution) {
            return response()->json([
                'message' => 'You are not authorized to mark this as a solution'
            ], 403);
        }

        $post->markAsSolution();

        return response()->json([
            'message' => 'Post marked as solution',
            'data' => [
                'is_solution' => true,
            ]
        ]);
    }
}
