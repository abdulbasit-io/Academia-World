<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Academia World API",
 *     version="1.0.0",
 *     description="**Academia World** is a comprehensive academic event management and collaboration platform designed to facilitate scholarly networking, conference organization, and academic resource sharing.\n\n## Features\n\n- **Event Management**: Create, manage, and participate in academic conferences, workshops, and seminars\n- **Academic Networking**: Connect with fellow researchers and build professional relationships\n- **Resource Sharing**: Upload, share, and access academic materials with granular permission controls\n- **Discussion Forums**: Engage in structured academic discourse with moderation capabilities\n- **User Management**: Comprehensive user profiles with institutional affiliations and academic credentials\n\n## Authentication\n\nThis API uses **Laravel Sanctum** with both cookie-based and token-based authentication:\n\n- **Cookie Authentication**: Automatically handled for web clients after login\n- **Bearer Token**: Use `Authorization: Bearer {token}` header for API clients\n\n## Base URL\n\n- **Development**: `http://localhost:8000`\n- **Production**: `https://your-domain.com`\n\n## Rate Limiting\n\n- **General API**: 60 requests per minute\n- **Authentication**: 5 requests per minute\n\n## Error Handling\n\nAll endpoints return consistent error responses with HTTP status codes and descriptive messages.",
 *     @OA\Contact(
 *         name="Academia World Support",
 *         email="support@academiaworld.com"
 *     ),
 *     @OA\License(
 *         name="MIT License",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\Server(
 *     url="https://api.academiaworld.com",
 *     description="Production Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum token authentication via Bearer token. Include the token in the Authorization header: `Bearer {your-token}`"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="cookieAuth",
 *     type="apiKey",
 *     in="cookie",
 *     name="academia_world_token",
 *     description="Laravel Sanctum cookie-based authentication. This cookie is automatically set upon successful login and should be included in subsequent requests. Requires CSRF token for state-changing operations."
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="Academic User",
 *     description="Represents an academic user profile with institutional affiliations and academic credentials",
 *     required={"uuid", "first_name", "last_name", "email", "institution", "account_status", "is_admin", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Unique identifier for the user"),
 *     @OA\Property(property="first_name", type="string", maxLength=255, example="John", description="User's first name"),
 *     @OA\Property(property="last_name", type="string", maxLength=255, example="Doe", description="User's last name"),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="john.doe@university.edu", description="User's email address (must be unique)"),
 *     @OA\Property(property="institution", type="string", maxLength=255, example="University of Technology", description="Academic institution affiliation"),
 *     @OA\Property(property="department", type="string", maxLength=255, example="Computer Science", nullable=true, description="Department within the institution"),
 *     @OA\Property(property="position", type="string", maxLength=255, example="Professor", nullable=true, description="Academic position or title"),
 *     @OA\Property(property="bio", type="string", maxLength=1000, nullable=true, example="Research interests include AI, machine learning, and academic collaboration platforms.", description="User's biography or research interests"),
 *     @OA\Property(property="website", type="string", format="url", maxLength=255, nullable=true, example="https://johndoe.university.edu", description="Personal or professional website"),
 *     @OA\Property(property="phone", type="string", maxLength=20, nullable=true, example="+1-555-123-4567", description="Contact phone number"),
 *     @OA\Property(property="avatar", type="string", format="url", nullable=true, example="https://api.academiaworld.com/storage/avatars/user-123.jpg", description="URL to user's profile picture"),
 *     @OA\Property(
 *         property="social_links", 
 *         type="object", 
 *         nullable=true, 
 *         description="Social media and professional network links",
 *         @OA\Property(property="linkedin", type="string", format="url", example="https://linkedin.com/in/johndoe"),
 *         @OA\Property(property="twitter", type="string", format="url", example="https://twitter.com/johndoe"),
 *         @OA\Property(property="orcid", type="string", example="0000-0000-0000-0000"),
 *         @OA\Property(property="google_scholar", type="string", format="url", example="https://scholar.google.com/citations?user=abc123"),
 *         @OA\Property(property="researchgate", type="string", format="url", example="https://researchgate.net/profile/John_Doe")
 *     ),
 *     @OA\Property(property="account_status", type="string", enum={"pending", "active", "suspended"}, example="active", description="Current status of the user account"),
 *     @OA\Property(property="is_admin", type="boolean", example=false, description="Whether the user has administrative privileges"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2025-01-15T10:30:00Z", description="When the email was verified"),
 *     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true, example="2025-08-06T09:15:00Z", description="Last login timestamp"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T10:30:00Z", description="Account creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-06T09:15:00Z", description="Last profile update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="Event",
 *     type="object",
 *     title="Academic Event",
 *     description="Represents an academic event such as a conference, workshop, seminar, or research presentation",
 *     required={"uuid", "title", "description", "start_date", "end_date", "location_type", "status", "visibility", "host", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Unique identifier for the event"),
 *     @OA\Property(property="title", type="string", maxLength=255, example="AI in Academic Research Conference 2025", description="Event title"),
 *     @OA\Property(property="description", type="string", example="Annual conference exploring the latest developments in artificial intelligence applications within academic research. Join leading researchers, practitioners, and students for presentations, workshops, and networking opportunities.", description="Detailed event description"),
 *     @OA\Property(property="start_date", type="string", format="date-time", example="2025-09-15T09:00:00Z", description="Event start date and time"),
 *     @OA\Property(property="end_date", type="string", format="date-time", example="2025-09-17T17:00:00Z", description="Event end date and time"),
 *     @OA\Property(property="timezone", type="string", maxLength=50, example="UTC", nullable=true, description="Event timezone"),
 *     @OA\Property(
 *         property="location_type", 
 *         type="string", 
 *         enum={"physical", "virtual", "hybrid"}, 
 *         example="hybrid", 
 *         description="Type of event location: physical (in-person), virtual (online), or hybrid (both)"
 *     ),
 *     @OA\Property(property="location", type="string", maxLength=500, example="University Conference Center, 123 Academic Way, Tech City, TC 12345", nullable=true, description="Physical location address (required for physical and hybrid events)"),
 *     @OA\Property(property="virtual_link", type="string", format="url", example="https://zoom.us/j/123456789?pwd=abcdef", nullable=true, description="Virtual meeting link (required for virtual and hybrid events)"),
 *     @OA\Property(property="meeting_link", type="string", format="url", example="https://zoom.us/j/123456789?pwd=abcdef", nullable=true, description="Alternative meeting link field"),
 *     @OA\Property(property="capacity", type="integer", minimum=1, maximum=50000, example=200, nullable=true, description="Maximum number of attendees (null for unlimited)"),
 *     @OA\Property(property="available_spots", type="integer", minimum=0, example=150, description="Number of available registration spots remaining"),
 *     @OA\Property(property="is_full", type="boolean", example=false, description="Whether the event has reached capacity"),
 *     @OA\Property(
 *         property="visibility", 
 *         type="string", 
 *         enum={"public", "private"}, 
 *         example="public", 
 *         description="Event visibility: public (discoverable by all users) or private (invitation only)"
 *     ),
 *     @OA\Property(
 *         property="status", 
 *         type="string", 
 *         enum={"draft", "published", "cancelled", "completed", "banned"}, 
 *         example="published", 
 *         description="Event status: draft (not visible), published (active), cancelled, completed, or banned (by admin)"
 *     ),
 *     @OA\Property(
 *         property="tags", 
 *         type="array", 
 *         @OA\Items(type="string", maxLength=50), 
 *         example={"AI", "Machine Learning", "Research", "Conference", "Networking"}, 
 *         description="Tags for categorizing and searching events"
 *     ),
 *     @OA\Property(property="poster", type="string", format="url", nullable=true, example="https://api.academiaworld.com/storage/posters/event-123.jpg", description="URL to event poster image"),
 *     @OA\Property(property="agenda", type="string", nullable=true, example="9:00 AM - Registration\n10:00 AM - Keynote\n11:30 AM - Panel Discussion", description="Event agenda or schedule"),
 *     @OA\Property(property="requirements", type="string", maxLength=1000, nullable=true, example="Participants should have basic knowledge of machine learning concepts. Laptops required for hands-on sessions.", description="Prerequisites or requirements for attendees"),
 *     @OA\Property(property="registration_deadline", type="string", format="date-time", nullable=true, example="2025-09-10T23:59:59Z", description="Last date for registration"),
 *     @OA\Property(property="host", ref="#/components/schemas/User", description="User who created and hosts the event"),
 *     @OA\Property(property="registrations_count", type="integer", example=50, description="Total number of registered attendees"),
 *     @OA\Property(property="resources_count", type="integer", example=5, description="Number of associated resources (presentations, papers, etc.)"),
 *     @OA\Property(property="forums_count", type="integer", example=3, description="Number of discussion forums for this event"),
 *     @OA\Property(property="user_registered", type="boolean", example=true, description="Whether the current user is registered for this event (only present for authenticated requests)"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-15T14:30:00Z", description="Event creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-06T09:15:00Z", description="Last event update timestamp"),
 *     @OA\Property(property="moderated_at", type="string", format="date-time", nullable=true, example="2025-07-16T10:00:00Z", description="When the event was reviewed by moderators"),
 *     @OA\Property(property="banned_at", type="string", format="date-time", nullable=true, description="When the event was banned (if applicable)"),
 *     @OA\Property(property="ban_reason", type="string", nullable=true, description="Reason for banning the event (if applicable)")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     title="Pagination Metadata",
 *     description="Standard pagination information for paginated responses",
 *     required={"current_page", "last_page", "per_page", "total"},
 *     @OA\Property(property="current_page", type="integer", minimum=1, example=1, description="Current page number"),
 *     @OA\Property(property="last_page", type="integer", minimum=1, example=5, description="Last available page number"),
 *     @OA\Property(property="per_page", type="integer", minimum=1, maximum=100, example=15, description="Number of items per page"),
 *     @OA\Property(property="total", type="integer", minimum=0, example=75, description="Total number of items across all pages"),
 *     @OA\Property(property="from", type="integer", minimum=1, nullable=true, example=1, description="First item number on current page"),
 *     @OA\Property(property="to", type="integer", minimum=1, nullable=true, example=15, description="Last item number on current page"),
 *     @OA\Property(property="has_more_pages", type="boolean", example=true, description="Whether there are more pages available")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Standard error response format used across all endpoints",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="The given data was invalid.", description="Human-readable error message"),
 *     @OA\Property(
 *         property="errors", 
 *         type="object", 
 *         nullable=true, 
 *         description="Detailed validation errors (only present for 422 validation errors)",
 *         @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email has already been taken.")),
 *         @OA\Property(property="start_date", type="array", @OA\Items(type="string", example="The start date must be a date after today."))
 *     ),
 *     @OA\Property(property="error_code", type="string", nullable=true, example="VALIDATION_FAILED", description="Machine-readable error code"),
 *     @OA\Property(property="debug_info", type="object", nullable=true, description="Additional debug information (only in development mode)")
 * )
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     title="Success Response",
 *     description="Standard success response format",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="Operation completed successfully.", description="Success message"),
 *     @OA\Property(property="data", type="object", nullable=true, description="Response data (varies by endpoint)")
 * )
 *
 * @OA\Schema(
 *     schema="UserConnection",
 *     type="object",
 *     title="User Connection",
 *     description="Represents a professional connection between two academic users",
 *     required={"uuid", "requester", "requested", "status", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="aa0j3800-e29b-41d4-a716-446655440005", description="Unique identifier for the connection"),
 *     @OA\Property(property="requester", ref="#/components/schemas/User", description="User who initiated the connection request"),
 *     @OA\Property(property="requested", ref="#/components/schemas/User", description="User who received the connection request"),
 *     @OA\Property(
 *         property="status", 
 *         type="string", 
 *         enum={"pending", "accepted", "declined", "blocked"}, 
 *         example="accepted", 
 *         description="Connection status"
 *     ),
 *     @OA\Property(property="message", type="string", maxLength=500, nullable=true, example="I'd like to connect to discuss our mutual research interests in AI ethics.", description="Optional message from requester"),
 *     @OA\Property(property="connected_at", type="string", format="date-time", nullable=true, example="2025-08-02T10:30:00Z", description="When the connection was accepted"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-01T15:00:00Z", description="When the connection request was created"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-02T10:30:00Z", description="Last connection update")
 * )
 *
 * @OA\Schema(
 *     schema="UserStats",
 *     type="object",
 *     title="User Statistics",
 *     description="Comprehensive statistics for a user's platform activity",
 *     required={"hosted_events_total", "registered_events_total", "profile_completion"},
 *     @OA\Property(property="hosted_events_total", type="integer", minimum=0, example=12, description="Total number of events hosted by the user"),
 *     @OA\Property(property="hosted_events_upcoming", type="integer", minimum=0, example=3, description="Number of upcoming hosted events"),
 *     @OA\Property(property="hosted_events_completed", type="integer", minimum=0, example=9, description="Number of completed hosted events"),
 *     @OA\Property(property="registered_events_total", type="integer", minimum=0, example=28, description="Total number of events user has registered for"),
 *     @OA\Property(property="registered_events_upcoming", type="integer", minimum=0, example=5, description="Number of upcoming registered events"),
 *     @OA\Property(property="total_attendees_hosted", type="integer", minimum=0, example=450, description="Total attendees across all hosted events"),
 *     @OA\Property(property="connections_count", type="integer", minimum=0, example=67, description="Number of professional connections"),
 *     @OA\Property(property="forum_posts_count", type="integer", minimum=0, example=23, description="Number of forum posts made"),
 *     @OA\Property(property="resources_uploaded", type="integer", minimum=0, example=15, description="Number of resources uploaded"),
 *     @OA\Property(property="resources_downloads", type="integer", minimum=0, example=1245, description="Total downloads of user's resources"),
 *     @OA\Property(property="profile_completion", type="integer", minimum=0, maximum=100, example=85, description="Profile completion percentage"),
 *     @OA\Property(property="reputation_score", type="integer", minimum=0, example=340, description="User reputation score based on activity and contributions")
 * )
 *
 * @OA\Schema(
 *     schema="EventRegistration",
 *     type="object",
 *     title="Event Registration",
 *     description="Represents a user's registration for an academic event",
 *     required={"uuid", "event", "status", "registered_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="660f9400-e29b-41d4-a716-446655440001", description="Unique identifier for the registration"),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", description="The event being registered for"),
 *     @OA\Property(property="user", ref="#/components/schemas/User", description="The registered user (only included in admin views)"),
 *     @OA\Property(
 *         property="status", 
 *         type="string", 
 *         enum={"registered", "cancelled", "waitlisted"}, 
 *         example="registered", 
 *         description="Registration status"
 *     ),
 *     @OA\Property(property="notes", type="string", maxLength=1000, nullable=true, example="Looking forward to the AI sessions!", description="Optional notes from the registrant"),
 *     @OA\Property(property="registered_at", type="string", format="date-time", example="2025-08-01T15:30:00Z", description="When the registration was completed"),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true, description="When the registration was cancelled (if applicable)"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-01T15:30:00Z", description="Registration record creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-01T15:30:00Z", description="Last registration update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="EventResource",
 *     type="object",
 *     title="Event Resource",
 *     description="Represents an academic resource (file, document, presentation) associated with an event",
 *     required={"uuid", "title", "original_filename", "file_type", "file_size", "resource_type", "is_public", "is_downloadable", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="770g0500-e29b-41d4-a716-446655440002", description="Unique identifier for the resource"),
 *     @OA\Property(property="title", type="string", maxLength=255, example="Conference Keynote: AI Ethics in Research", description="Resource title"),
 *     @OA\Property(property="description", type="string", maxLength=1000, nullable=true, example="Keynote presentation exploring ethical considerations in AI research methodologies.", description="Detailed resource description"),
 *     @OA\Property(property="original_filename", type="string", example="AI_Ethics_Keynote_2025.pdf", description="Original name of the uploaded file"),
 *     @OA\Property(property="file_type", type="string", example="pdf", description="File extension/type"),
 *     @OA\Property(property="mime_type", type="string", example="application/pdf", description="MIME type of the file"),
 *     @OA\Property(property="file_size", type="integer", example=2048576, description="File size in bytes"),
 *     @OA\Property(property="file_size_formatted", type="string", example="2.0 MB", description="Human-readable file size"),
 *     @OA\Property(property="file_url", type="string", format="url", example="https://api.academiaworld.com/storage/resources/ai-ethics-keynote.pdf", description="Public URL to access the resource"),
 *     @OA\Property(
 *         property="resource_type", 
 *         type="string", 
 *         enum={"presentation", "paper", "recording", "agenda", "poster", "handout", "dataset", "code", "other"}, 
 *         example="presentation", 
 *         description="Category of the resource"
 *     ),
 *     @OA\Property(property="is_public", type="boolean", example=true, description="Whether the resource is publicly accessible"),
 *     @OA\Property(property="is_downloadable", type="boolean", example=true, description="Whether users can download the resource"),
 *     @OA\Property(property="requires_registration", type="boolean", example=false, description="Whether access requires event registration"),
 *     @OA\Property(property="download_count", type="integer", example=156, description="Number of times the resource has been downloaded"),
 *     @OA\Property(property="view_count", type="integer", example=342, description="Number of times the resource has been viewed"),
 *     @OA\Property(property="status", type="string", enum={"active", "archived", "deleted"}, example="active", description="Resource status"),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true, description="Associated event (when included in response)"),
 *     @OA\Property(property="uploaded_by", ref="#/components/schemas/User", nullable=true, description="User who uploaded the resource"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-01T10:00:00Z", description="Resource upload timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-01T10:00:00Z", description="Last resource update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="DiscussionForum",
 *     type="object",
 *     title="Discussion Forum",
 *     description="Represents a discussion forum associated with an academic event",
 *     required={"uuid", "title", "type", "is_active", "is_moderated", "post_count", "participant_count", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="880h1600-e29b-41d4-a716-446655440003", description="Unique identifier for the forum"),
 *     @OA\Property(property="title", type="string", maxLength=255, example="AI Research Methodology Discussion", description="Forum title"),
 *     @OA\Property(property="description", type="string", maxLength=1000, nullable=true, example="Discuss research methodologies, share insights, and ask questions about AI research approaches.", description="Forum description"),
 *     @OA\Property(
 *         property="type", 
 *         type="string", 
 *         enum={"general", "q_and_a", "networking", "feedback", "technical", "academic"}, 
 *         example="academic", 
 *         description="Forum category: general discussion, Q&A, networking, feedback collection, technical support, or academic discourse"
 *     ),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Whether the forum is currently active"),
 *     @OA\Property(property="is_moderated", type="boolean", example=true, description="Whether posts require moderation before appearing"),
 *     @OA\Property(property="post_count", type="integer", example=47, description="Total number of posts in the forum"),
 *     @OA\Property(property="participant_count", type="integer", example=23, description="Number of unique users who have participated"),
 *     @OA\Property(property="last_activity_at", type="string", format="date-time", nullable=true, example="2025-08-06T14:20:00Z", description="Timestamp of the last post or activity"),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true, description="Associated event (when included)"),
 *     @OA\Property(property="creator", ref="#/components/schemas/User", nullable=true, description="User who created the forum"),
 *     @OA\Property(property="latest_post", type="object", nullable=true, description="Most recent post in the forum",
 *         @OA\Property(property="uuid", type="string", format="uuid"),
 *         @OA\Property(property="content", type="string"),
 *         @OA\Property(property="user", ref="#/components/schemas/User"),
 *         @OA\Property(property="created_at", type="string", format="date-time")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-20T09:00:00Z", description="Forum creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-06T14:20:00Z", description="Last forum update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="ForumPost",
 *     type="object",
 *     title="Forum Post",
 *     description="Represents a post or reply in a discussion forum",
 *     required={"uuid", "content", "is_pinned", "is_solution", "likes_count", "replies_count", "user", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="990i2700-e29b-41d4-a716-446655440004", description="Unique identifier for the post"),
 *     @OA\Property(property="content", type="string", minLength=1, maxLength=10000, example="I'm particularly interested in the ethical implications of using AI in peer review processes. Has anyone encountered studies on this topic?", description="Post content"),
 *     @OA\Property(property="is_pinned", type="boolean", example=false, description="Whether the post is pinned to the top of the forum"),
 *     @OA\Property(property="is_solution", type="boolean", example=false, description="Whether the post is marked as a solution (for Q&A forums)"),
 *     @OA\Property(property="likes_count", type="integer", example=12, description="Number of likes received"),
 *     @OA\Property(property="replies_count", type="integer", example=5, description="Number of direct replies"),
 *     @OA\Property(property="user_has_liked", type="boolean", example=false, description="Whether the current user has liked this post"),
 *     @OA\Property(property="user", ref="#/components/schemas/User", description="User who created the post"),
 *     @OA\Property(property="forum", ref="#/components/schemas/DiscussionForum", nullable=true, description="Associated forum (when included)"),
 *     @OA\Property(property="parent_post", type="object", nullable=true, description="Parent post if this is a reply",
 *         @OA\Property(property="uuid", type="string", format="uuid"),
 *         @OA\Property(property="content", type="string"),
 *         @OA\Property(property="user", ref="#/components/schemas/User")
 *     ),
 *     @OA\Property(property="replies", type="array", @OA\Items(ref="#/components/schemas/ForumPost"), description="Direct replies to this post (when included)"),
 *     @OA\Property(property="edited_at", type="string", format="date-time", nullable=true, example="2025-08-02T16:45:00Z", description="When the post was last edited"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-01T14:30:00Z", description="Post creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-02T16:45:00Z", description="Last post update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="AdminLog",
 *     type="object",
 *     title="Administrative Log Entry",
 *     description="Represents an administrative action log entry for audit purposes",
 *     required={"uuid", "action", "target_type", "description", "ip_address", "severity", "admin", "created_at"},
 *     @OA\Property(property="uuid", type="string", format="uuid", example="bb0k4900-e29b-41d4-a716-446655440006", description="Unique identifier for the log entry"),
 *     @OA\Property(
 *         property="action", 
 *         type="string", 
 *         enum={"user_ban", "user_unban", "user_promote", "user_demote", "event_ban", "event_unban", "event_delete", "post_delete", "admin_create"}, 
 *         example="event_ban", 
 *         description="Type of administrative action performed"
 *     ),
 *     @OA\Property(
 *         property="target_type", 
 *         type="string", 
 *         enum={"user", "event", "post", "resource", "forum"}, 
 *         example="event", 
 *         description="Type of entity the action was performed on"
 *     ),
 *     @OA\Property(property="target_uuid", type="string", format="uuid", nullable=true, example="550e8400-e29b-41d4-a716-446655440000", description="UUID of the target entity"),
 *     @OA\Property(property="description", type="string", example="Banned event 'Inappropriate Conference' for violating community guidelines", description="Human-readable description of the action"),
 *     @OA\Property(property="changes", type="object", nullable=true, description="JSON object containing the changes made",
 *         @OA\Property(property="old_status", type="string", example="published"),
 *         @OA\Property(property="new_status", type="string", example="banned"),
 *         @OA\Property(property="reason", type="string", example="Inappropriate content")
 *     ),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Additional metadata about the action"),
 *     @OA\Property(property="ip_address", type="string", format="ipv4", example="192.168.1.100", description="IP address of the admin who performed the action"),
 *     @OA\Property(
 *         property="severity", 
 *         type="string", 
 *         enum={"low", "medium", "high", "critical"}, 
 *         example="high", 
 *         description="Severity level of the action"
 *     ),
 *     @OA\Property(property="admin", ref="#/components/schemas/User", description="Administrator who performed the action"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-06T10:15:00Z", description="When the action was performed"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-06T10:15:00Z", description="Last update to the log entry")
 * )
 *
 * @OA\Schema(
 *     schema="PlatformHealth",
 *     type="object",
 *     title="Platform Health Status",
 *     description="Overall platform health and status information for administrators",
 *     required={"status", "database", "cache", "queue", "storage", "event_metrics", "user_metrics", "timestamp"},
 *     @OA\Property(property="status", type="string", enum={"healthy", "degraded", "unhealthy"}, example="healthy", description="Overall platform status"),
 *     @OA\Property(property="database", type="object", description="Database connection status",
 *         @OA\Property(property="status", type="string", enum={"connected", "disconnected"}, example="connected"),
 *         @OA\Property(property="response_time_ms", type="integer", example=12)
 *     ),
 *     @OA\Property(property="cache", type="object", description="Cache system status",
 *         @OA\Property(property="status", type="string", enum={"connected", "disconnected"}, example="connected"),
 *         @OA\Property(property="response_time_ms", type="integer", example=3)
 *     ),
 *     @OA\Property(property="queue", type="object", description="Queue system status",
 *         @OA\Property(property="pending_jobs", type="integer", example=12),
 *         @OA\Property(property="failed_jobs", type="integer", example=0),
 *         @OA\Property(property="status", type="string", enum={"operational", "backlogged", "failing"}, example="operational")
 *     ),
 *     @OA\Property(property="storage", type="object", description="Storage system status",
 *         @OA\Property(property="disk_usage_percentage", type="number", format="float", example=45.7),
 *         @OA\Property(property="available_space_gb", type="number", format="float", example=123.4)
 *     ),
 *     @OA\Property(property="event_metrics", type="object", description="Event-related metrics",
 *         @OA\Property(property="total_events", type="integer", example=1247),
 *         @OA\Property(property="active_events", type="integer", example=45),
 *         @OA\Property(property="upcoming_events", type="integer", example=78)
 *     ),
 *     @OA\Property(property="user_metrics", type="object", description="User-related metrics",
 *         @OA\Property(property="total_users", type="integer", example=5432),
 *         @OA\Property(property="active_users_24h", type="integer", example=234),
 *         @OA\Property(property="banned_users", type="integer", example=12),
 *         @OA\Property(property="pending_users", type="integer", example=8)
 *     ),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2025-08-06T15:30:00Z", description="When the health check was performed")
 * )
 */
class ApiDocumentationController extends Controller
{
    // This class exists solely for OpenAPI documentation organization
}