<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ForumPostController extends Controller
{
    public function __construct(private AnalyticsService $analyticsService)
    {
        // Constructor injection for AnalyticsService
    }
    /**
     * @OA\Get(
     *     path="/api/v1/forums/{forum}/posts",
     *     summary="Get all posts in a forum",
     *     description="Retrieves all top-level posts in a forum with replies and pagination",
     *     operationId="getForumPosts",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="forum",
     *         in="path",
     *         description="Forum UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Posts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Posts retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="content", type="string"),
     *                     @OA\Property(property="likes_count", type="integer"),
     *                     @OA\Property(property="replies_count", type="integer"),
     *                     @OA\Property(property="edited_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="user", type="object", nullable=true,
     *                         @OA\Property(property="uuid", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string")
     *                     ),
     *                     @OA\Property(property="replies", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="uuid", type="string", format="uuid"),
     *                             @OA\Property(property="content", type="string"),
     *                             @OA\Property(property="created_at", type="string", format="date-time"),
     *                             @OA\Property(property="user", type="object", nullable=true,
     *                                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                                 @OA\Property(property="name", type="string")
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
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
    public function index(DiscussionForum $forum): JsonResponse
    {
        $posts = $forum->topLevelPosts()
            ->with(['user:id,uuid,first_name,last_name', 'replies.user:id,uuid,first_name,last_name'])
            ->withCount('replies')
            ->paginate(20);

        return response()->json([
            'message' => 'Posts retrieved successfully',
            'data' => collect($posts->items())->map(function($post) {
                return [
                    'uuid' => $post->uuid,
                    'content' => $post->content,
                    'likes_count' => $post->likes_count,
                    'replies_count' => $post->replies_count,
                    'edited_at' => $post->edited_at,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'user' => $post->user ? [
                        'uuid' => $post->user->uuid,
                        'name' => $post->user->first_name . ' ' . $post->user->last_name,
                    ] : null,
                    'replies' => $post->replies->map(function($reply) {
                        return [
                            'uuid' => $reply->uuid,
                            'content' => $reply->content,
                            'created_at' => $reply->created_at,
                            'user' => $reply->user ? [
                                'uuid' => $reply->user->uuid,
                                'name' => $reply->user->first_name . ' ' . $reply->user->last_name,
                            ] : null,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/forums/{forum}/posts",
     *     summary="Create a new post in a forum",
     *     description="Creates a new post or reply in a forum",
     *     operationId="createForumPost",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="forum",
     *         in="path",
     *         description="Forum UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", minLength=1, maxLength=10000, example="This is my post content"),
     *             @OA\Property(property="parent_uuid", type="string", format="uuid", nullable=true, example="550e8400-e29b-41d4-a716-446655440000", description="UUID of parent post for replies")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Post created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="is_pinned", type="boolean"),
     *                 @OA\Property(property="is_solution", type="boolean"),
     *                 @OA\Property(property="likes_count", type="integer"),
     *                 @OA\Property(property="replies_count", type="integer"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to post in forum",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to post in this forum")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid parent post",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid parent post"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
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
            'parent_uuid' => ['nullable', 'uuid', 'exists:forum_posts,uuid']
        ]);

        // If parent_uuid is provided, ensure it belongs to this forum
        if (isset($validated['parent_uuid']) && $validated['parent_uuid']) {
            $parentPost = ForumPost::where('uuid', $validated['parent_uuid'])->first();
            if (!$parentPost || $parentPost->forum_id !== $forum->id) {
                return response()->json([
                    'message' => 'Invalid parent post'
                ], 422);
            }
            // Use the actual ID for the database relationship
            $validated['parent_id'] = $parentPost->id;
        }

        $post = ForumPost::create([
            'forum_id' => $forum->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'],
        ]);

        $post->load(['user:id,uuid,first_name,last_name']);

        // Track analytics
        $this->analyticsService->trackEngagement('post_creation', [
            'entity_type' => 'forum_post',
            'entity_id' => $post->id,
            'metadata' => [
                'forum_id' => $forum->id,
                'parent_id' => $validated['parent_id'] ?? null,
            ]
        ]);

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
                    'uuid' => $post->user->uuid,
                    'name' => $post->user->first_name . ' ' . $post->user->last_name
                ],
                'created_at' => $post->created_at,
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/posts/{post}",
     *     summary="Get a specific post with its replies",
     *     description="Retrieves a specific forum post with all its replies and metadata",
     *     operationId="getForumPost",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Post UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="is_pinned", type="boolean"),
     *                 @OA\Property(property="is_solution", type="boolean"),
     *                 @OA\Property(property="likes_count", type="integer"),
     *                 @OA\Property(property="replies_count", type="integer"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="forum", type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="type", type="string")
     *                 ),
     *                 @OA\Property(property="replies", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="uuid", type="string", format="uuid"),
     *                         @OA\Property(property="content", type="string"),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="uuid", type="string", format="uuid"),
     *                             @OA\Property(property="name", type="string")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function show(ForumPost $post): JsonResponse
    {
        $post->load([
            'user:id,uuid,first_name,last_name',
            'replies.user:id,uuid,first_name,last_name',
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
                    'uuid' => $post->user->uuid,
                    'name' => $post->user->first_name . ' ' . $post->user->last_name
                ],
                'replies' => $post->replies->map(function ($reply) {
                    return [
                        'uuid' => $reply->uuid,
                        'content' => $reply->content,
                        'is_solution' => $reply->is_solution,
                        'likes_count' => $reply->likes_count,
                        'user' => [
                            'uuid' => $reply->user->uuid,
                            'name' => $reply->user->first_name . ' ' . $reply->user->last_name
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
     * @OA\Put(
     *     path="/api/v1/posts/{post}",
     *     summary="Update a post",
     *     description="Updates a forum post. Only the post author can edit their posts.",
     *     operationId="updateForumPost",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Post UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", minLength=1, maxLength=10000, example="Updated post content")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="edited_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to edit post",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to edit this post")
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
     * @OA\Delete(
     *     path="/api/v1/posts/{post}",
     *     summary="Delete a post",
     *     description="Deletes a forum post. Only post author, forum host, or admin can delete posts.",
     *     operationId="deletePost",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Post UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to delete post",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to delete this post")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function destroy(Request $request, ForumPost $post): JsonResponse
    {
        // Check if user can delete this post
        if (!$post->canBeDeletedBy($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to delete this post'
            ], 403);
        }

        try {
            // Soft delete the post
            $post->delete();

            \Log::info('Forum post soft deleted', [
                'post_id' => $post->id,
                'post_uuid' => $post->uuid,
                'user_id' => $request->user()->id,
                'forum_id' => $post->forum_id
            ]);

            return response()->json([
                'message' => 'Post deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Forum post deletion failed', [
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete post',
                'error' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{post}/like",
     *     summary="Toggle like on a post",
     *     description="Like or unlike a forum post",
     *     operationId="toggleLikeForumPost",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Post UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post liked/unliked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post liked"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="liked", type="boolean"),
     *                 @OA\Property(property="likes_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function toggleLike(Request $request, ForumPost $post): JsonResponse
    {
        $result = $post->toggleLike($request->user());

        // Track analytics
        $this->analyticsService->trackEngagement('post_like', [
            'entity_type' => 'forum_post',
            'entity_id' => $post->id,
            'metadata' => [
                'liked' => $result['liked'],
                'forum_id' => $post->forum_id,
            ]
        ]);

        return response()->json([
            'message' => $result['liked'] ? 'Post liked' : 'Post unliked',
            'data' => $result
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{post}/pin",
     *     summary="Pin/unpin a post",
     *     description="Pin or unpin a forum post. Only event hosts and admins can pin posts.",
     *     operationId="togglePinForumPost",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Post UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post pinned/unpinned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post pinned"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="is_pinned", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to pin posts",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to pin posts")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function togglePin(Request $request, ForumPost $post): JsonResponse
    {
        // Load the necessary relationships
        $post->load(['forum.event']);
        
        // Check if user can pin posts (event host or admin)
        if (!$request->user()->is_admin && $request->user()->id !== $post->forum->event->host_id) {
            return response()->json([
                'message' => 'You are not authorized to pin posts'
            ], 403);
        }

        // Get current pin status and calculate new status
        $currentPinStatus = $post->is_pinned;
        $newPinStatus = !$currentPinStatus;
        
        // Update the pin status
        $post->update(['is_pinned' => $newPinStatus]);
        
        // Refresh the model to get the updated data
        $post->refresh();

        return response()->json([
            'message' => $newPinStatus ? 'Post pinned' : 'Post unpinned',
            'data' => [
                'is_pinned' => $post->is_pinned,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{post}/solution",
     *     summary="Mark post as solution",
     *     description="Mark a post as the solution in Q&A forums. Only post author, event host, or admin can mark solutions.",
     *     operationId="markPostAsSolution",
     *     tags={"Forum Posts"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Post UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post marked as solution successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post marked as solution"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="is_solution", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to mark as solution",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to mark this as a solution")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Not a Q&A forum",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Solutions can only be marked in Q&A forums")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function markAsSolution(Request $request, ForumPost $post): JsonResponse
    {
        // Load the necessary relationships
        $post->load(['forum.event', 'parent.user']);
        
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
