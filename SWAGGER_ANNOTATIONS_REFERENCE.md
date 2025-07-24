# API Documentation - Swagger Annotations for Remaining Controllers

## ForumController Additional Methods

```php
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
 */
public function store(Request $request, Event $event): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/forums/{forum}",
 *     tags={"Forums"},
 *     summary="Get forum details",
 *     description="Retrieve detailed information about a specific forum",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="forum",
 *         in="path",
 *         description="Forum UUID",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Forum details retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Forum not found")
 * )
 */
public function show(DiscussionForum $forum): JsonResponse

/**
 * @OA\Put(
 *     path="/api/v1/forums/{forum}",
 *     tags={"Forums"},
 *     summary="Update forum",
 *     description="Update forum details (event hosts and admins only)",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="forum",
 *         in="path",
 *         description="Forum UUID",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string", maxLength=255),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="is_active", type="boolean"),
 *             @OA\Property(property="is_moderated", type="boolean")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Forum updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Unauthorized to update this forum")
 * )
 */
public function update(Request $request, DiscussionForum $forum): JsonResponse

/**
 * @OA\Delete(
 *     path="/api/v1/forums/{forum}",
 *     tags={"Forums"},
 *     summary="Delete forum",
 *     description="Delete a forum (event hosts and admins only)",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="forum",
 *         in="path",
 *         description="Forum UUID",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Forum deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Unauthorized to delete this forum")
 * )
 */
public function destroy(Request $request, DiscussionForum $forum): JsonResponse
```

## ForumPostController Methods

```php
/**
 * @OA\Tag(
 *     name="Forum Posts",
 *     description="Forum post management and interactions"
 * )
 */

/**
 * @OA\Get(
 *     path="/api/v1/forums/{forum}/posts",
 *     tags={"Forum Posts"},
 *     summary="Get forum posts",
 *     description="Retrieve paginated posts for a specific forum",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="forum",
 *         in="path",
 *         description="Forum UUID",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number",
 *         required=false,
 *         @OA\Schema(type="integer", default=1)
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Posts per page",
 *         required=false,
 *         @OA\Schema(type="integer", default=15)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Posts retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function index(DiscussionForum $forum): JsonResponse

/**
 * @OA\Post(
 *     path="/api/v1/forums/{forum}/posts",
 *     tags={"Forum Posts"},
 *     summary="Create a new post",
 *     description="Create a new post in a forum",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="forum",
 *         in="path",
 *         description="Forum UUID",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"content"},
 *             @OA\Property(property="content", type="string"),
 *             @OA\Property(property="parent_id", type="integer", description="Reply to post ID")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Post created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function store(Request $request, DiscussionForum $forum): JsonResponse

/**
 * @OA\Post(
 *     path="/api/v1/posts/{post}/like",
 *     tags={"Forum Posts"},
 *     summary="Toggle post like",
 *     description="Like or unlike a forum post",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="post",
 *         in="path",
 *         description="Post ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Post like toggled successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function toggleLike(Request $request, ForumPost $post): JsonResponse

/**
 * @OA\Post(
 *     path="/api/v1/posts/{post}/pin",
 *     tags={"Forum Posts"},
 *     summary="Pin or unpin post",
 *     description="Toggle post pin status (moderators only)",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="post",
 *         in="path",
 *         description="Post ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Post pin status toggled successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Unauthorized to pin posts")
 * )
 */
public function togglePin(Request $request, ForumPost $post): JsonResponse

/**
 * @OA\Post(
 *     path="/api/v1/posts/{post}/solution",
 *     tags={"Forum Posts"},
 *     summary="Mark post as solution",
 *     description="Mark a post as the solution to a question",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="post",
 *         in="path",
 *         description="Post ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Post marked as solution successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function markAsSolution(Request $request, ForumPost $post): JsonResponse
```

## UserConnectionController Methods

```php
/**
 * @OA\Tag(
 *     name="User Connections",
 *     description="Academic networking and user connections"
 * )
 */

/**
 * @OA\Get(
 *     path="/api/v1/connections",
 *     tags={"User Connections"},
 *     summary="Get user connections",
 *     description="Retrieve user's accepted connections",
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Connections retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
public function index(Request $request): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/connections/pending",
 *     tags={"User Connections"},
 *     summary="Get pending connection requests",
 *     description="Retrieve incoming connection requests",
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Pending requests retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
public function pending(Request $request): JsonResponse

/**
 * @OA\Post(
 *     path="/api/v1/connections",
 *     tags={"User Connections"},
 *     summary="Send connection request",
 *     description="Send a connection request to another user",
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"addressee_id"},
 *             @OA\Property(property="addressee_id", type="string", format="uuid", description="Target user UUID"),
 *             @OA\Property(property="message", type="string", maxLength=500, description="Optional message")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Connection request sent successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function store(Request $request): JsonResponse

/**
 * @OA\Put(
 *     path="/api/v1/connections/{connection}/respond",
 *     tags={"User Connections"},
 *     summary="Respond to connection request",
 *     description="Accept or decline a connection request",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="connection",
 *         in="path",
 *         description="Connection ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(property="status", type="string", enum={"accepted", "declined"})
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Connection request responded to successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function respond(Request $request, UserConnection $connection): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/users/search",
 *     tags={"User Connections"},
 *     summary="Search users",
 *     description="Search for users to connect with",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         description="Search query",
 *         required=true,
 *         @OA\Schema(type="string", minLength=2)
 *     ),
 *     @OA\Parameter(
 *         name="institution",
 *         in="query",
 *         description="Filter by institution",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="department",
 *         in="query",
 *         description="Filter by department",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Users found successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
public function searchUsers(Request $request): JsonResponse
```

## Missing User Controller Methods

```php
/**
 * @OA\Tag(
 *     name="User Profile",
 *     description="User profile management operations"
 * )
 */

/**
 * @OA\Get(
 *     path="/api/v1/profile",
 *     tags={"User Profile"},
 *     summary="Get user profile",
 *     description="Retrieve current user's profile information",
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Profile retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function profile(Request $request): JsonResponse

/**
 * @OA\Put(
 *     path="/api/v1/profile",
 *     tags={"User Profile"},
 *     summary="Update user profile",
 *     description="Update current user's profile information",
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="first_name", type="string", maxLength=255),
 *             @OA\Property(property="last_name", type="string", maxLength=255),
 *             @OA\Property(property="bio", type="string"),
 *             @OA\Property(property="institution", type="string"),
 *             @OA\Property(property="department", type="string"),
 *             @OA\Property(property="position", type="string"),
 *             @OA\Property(property="website", type="string", format="url"),
 *             @OA\Property(property="phone", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Profile updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function updateProfile(Request $request): JsonResponse

/**
 * @OA\Post(
 *     path="/api/v1/profile/avatar",
 *     tags={"User Profile"},
 *     summary="Upload avatar",
 *     description="Upload user avatar image",
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="avatar", type="string", format="binary", description="Avatar image file")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Avatar uploaded successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function updateAvatar(Request $request): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/profile/stats",
 *     tags={"User Profile"},
 *     summary="Get user statistics",
 *     description="Retrieve user's activity statistics",
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Statistics retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="events_hosted", type="integer"),
 *                 @OA\Property(property="events_attended", type="integer"),
 *                 @OA\Property(property="forum_posts", type="integer"),
 *                 @OA\Property(property="connections", type="integer")
 *             )
 *         )
 *     )
 * )
 */
public function stats(Request $request): JsonResponse
```

## Event Interaction Methods (Missing from EventController)

```php
/**
 * @OA\Post(
 *     path="/api/v1/events/{event}/register",
 *     tags={"Events"},
 *     summary="Register for event",
 *     description="Register current user for an event",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="event",
 *         in="path",
 *         description="Event UUID",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="notes", type="string", description="Optional registration notes")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Successfully registered for event",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=409, description="Already registered for this event")
 * )
 */
public function register(Request $request, Event $event): JsonResponse

/**
 * @OA\Delete(
 *     path="/api/v1/events/{event}/unregister",
 *     tags={"Events"},
 *     summary="Unregister from event",
 *     description="Cancel user's registration for an event",
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
 *         description="Successfully unregistered from event",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Not registered for this event")
 * )
 */
public function unregister(Request $request, Event $event): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/events/{event}/attendees",
 *     tags={"Events"},
 *     summary="Get event attendees",
 *     description="Retrieve list of users registered for an event",
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
 *         description="Attendees retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
public function attendees(Request $request, Event $event): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/my-events",
 *     tags={"Events"},
 *     summary="Get user's hosted events",
 *     description="Retrieve events hosted by the current user",
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="User events retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
public function myEvents(Request $request): JsonResponse

/**
 * @OA\Get(
 *     path="/api/v1/my-registrations",
 *     tags={"Events"},
 *     summary="Get user's event registrations",
 *     description="Retrieve events the current user is registered for",
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="User registrations retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
public function myRegistrations(Request $request): JsonResponse
```

---

## Summary of Documentation Status

### ✅ COMPLETED
- **AdminController**: All 11 methods fully documented
- **AuthController**: Previously completed (9 methods)
- **EventController**: Core CRUD completed (3 methods)
- **ResourceController**: All methods completed (6 methods)

### 🔄 NEXT STEPS
1. Apply the above annotations to the remaining controller files
2. Test documentation generation after each controller
3. Validate all endpoints match actual implementation
4. Add any missing schema definitions

### 📊 Total Coverage After Completion
- **Total Endpoints**: 58
- **Will Be Documented**: 58 (100%)
- **Current Status**: AdminController complete, others pending application
