<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Academia World API",
 *     version="1.0.0",
 *     description="API for Academia World - Academic Event Management Platform",
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
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum token authentication via Bearer token"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="cookieAuth",
 *     type="apiKey",
 *     in="cookie",
 *     name="academia_world_token",
 *     description="Laravel Sanctum token authentication via HTTP-only cookie. The token is automatically set on successful login and used for subsequent requests."
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="first_name", type="string", example="John"),
 *     @OA\Property(property="last_name", type="string", example="Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john.doe@university.edu"),
 *     @OA\Property(property="institution", type="string", example="University of Technology"),
 *     @OA\Property(property="department", type="string", example="Computer Science"),
 *     @OA\Property(property="position", type="string", example="Professor"),
 *     @OA\Property(property="bio", type="string", nullable=true),
 *     @OA\Property(property="website", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="avatar", type="string", nullable=true),
 *     @OA\Property(property="social_links", type="object", nullable=true),
 *     @OA\Property(property="account_status", type="string", enum={"pending", "active", "suspended"}),
 *     @OA\Property(property="is_admin", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Event",
 *     type="object",
 *     @OA\Property(property="uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="title", type="string", example="AI in Academic Research"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="start_date", type="string", format="date-time"),
 *     @OA\Property(property="end_date", type="string", format="date-time"),
 *     @OA\Property(property="location_type", type="string", enum={"physical", "virtual", "hybrid"}),
 *     @OA\Property(property="location", type="string", nullable=true),
 *     @OA\Property(property="virtual_link", type="string", nullable=true),
 *     @OA\Property(property="capacity", type="integer", nullable=true),
 *     @OA\Property(property="visibility", type="string", enum={"public", "private"}),
 *     @OA\Property(property="status", type="string", enum={"published", "banned"}),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="host", ref="#/components/schemas/User"),
 *     @OA\Property(property="registrations_count", type="integer"),
 *     @OA\Property(property="user_registered", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=75)
 * )
 *
 * @OA\Schema(
 *     schema="EventRegistration",
 *     type="object",
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="event", ref="#/components/schemas/Event"),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="registered_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="EventResource",
 *     type="object",
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="original_filename", type="string"),
 *     @OA\Property(property="file_type", type="string"),
 *     @OA\Property(property="file_size", type="integer"),
 *     @OA\Property(property="file_size_formatted", type="string"),
 *     @OA\Property(property="resource_type", type="string", enum={"presentation", "paper", "recording", "agenda", "other"}),
 *     @OA\Property(property="is_public", type="boolean"),
 *     @OA\Property(property="is_downloadable", type="boolean"),
 *     @OA\Property(property="requires_registration", type="boolean"),
 *     @OA\Property(property="download_count", type="integer"),
 *     @OA\Property(property="view_count", type="integer"),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true),
 *     @OA\Property(property="uploaded_by", ref="#/components/schemas/User", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ApiDocumentationController extends Controller
{
    // This class exists solely for OpenAPI documentation organization
}