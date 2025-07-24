<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserConnectionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/user-connections",
     *     summary="Get user's connections",
     *     description="Retrieves all accepted connections for the authenticated user",
     *     operationId="getUserConnections",
     *     tags={"User Connections"},
     *     @OA\Response(
     *         response=200,
     *         description="Connections retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connections retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="name", type="string", example="Dr. Jane Smith"),
     *                         @OA\Property(property="institution", type="string", example="MIT")
     *                     ),
     *                     @OA\Property(property="connected_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get accepted connections
        $connections = UserConnection::where(function ($query) use ($user) {
            $query->where('requester_id', $user->id)
                  ->orWhere('addressee_id', $user->id);
        })
        ->where('status', 'accepted')
        ->with(['requester:id,name,institution', 'addressee:id,name,institution'])
        ->get()
        ->map(function ($connection) use ($user) {
            $otherUser = $connection->requester_id === $user->id 
                ? $connection->addressee 
                : $connection->requester;
                
            return [
                'user' => [
                    'name' => $otherUser->name,
                    'institution' => $otherUser->institution,
                ],
                'connected_at' => $connection->responded_at,
            ];
        });

        return response()->json([
            'message' => 'Connections retrieved successfully',
            'data' => $connections
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-connections/pending",
     *     summary="Get pending connection requests",
     *     description="Retrieves all pending connection requests for the authenticated user",
     *     operationId="getPendingConnections",
     *     tags={"User Connections"},
     *     @OA\Response(
     *         response=200,
     *         description="Pending requests retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pending requests retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="requester", type="object",
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="institution", type="string")
     *                     ),
     *                     @OA\Property(property="sent_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $pendingRequests = UserConnection::where('addressee_id', $user->id)
            ->where('status', 'pending')
            ->with(['requester:id,name,institution'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'requester' => [
                        'name' => $connection->requester->name,
                        'institution' => $connection->requester->institution,
                    ],
                    'message' => $connection->message,
                    'requested_at' => $connection->created_at,
                ];
            });

        return response()->json([
            'message' => 'Pending requests retrieved successfully',
            'data' => $pendingRequests
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user-connections",
     *     summary="Send a connection request",
     *     description="Send a connection request to another user",
     *     operationId="sendConnectionRequest",
     *     tags={"User Connections"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_uuid"},
     *             @OA\Property(property="user_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="message", type="string", maxLength=500, example="I'd like to connect with you")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Connection request sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connection request sent successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="sent_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot connect to yourself",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You cannot connect to yourself")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Connection already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connection already exists")
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:500']
        ]);

        $targetUser = User::find($validated['user_id']);
        $currentUser = $request->user();

        // Check if trying to connect to self
        if ($targetUser->id === $currentUser->id) {
            return response()->json([
                'message' => 'You cannot connect to yourself'
            ], 422);
        }

        // Check if connection already exists
        $existingConnection = UserConnection::where(function ($query) use ($currentUser, $targetUser) {
            $query->where('requester_id', $currentUser->id)
                  ->where('addressee_id', $targetUser->id);
        })->orWhere(function ($query) use ($currentUser, $targetUser) {
            $query->where('requester_id', $targetUser->id)
                  ->where('addressee_id', $currentUser->id);
        })->first();

        if ($existingConnection) {
            $status = $existingConnection->status;
            $message = match($status) {
                'pending' => 'Connection request already pending',
                'accepted' => 'You are already connected to this user',
                'declined' => 'Connection request was previously declined',
                'blocked' => 'Connection is blocked',
                default => 'Connection already exists'
            };

            return response()->json(['message' => $message], 422);
        }

        $connection = UserConnection::create([
            'requester_id' => $currentUser->id,
            'addressee_id' => $targetUser->id,
            'status' => 'pending',
            'message' => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Connection request sent successfully',
            'data' => [
                'id' => $connection->id,
                'status' => $connection->status,
                'sent_at' => $connection->created_at,
            ]
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/user-connections/{connection}/respond",
     *     summary="Respond to a connection request",
     *     description="Accept or decline a connection request",
     *     operationId="respondToConnectionRequest",
     *     tags={"User Connections"},
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
     *             @OA\Property(property="status", type="string", enum={"accepted", "declined"}, example="accepted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connection request responded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connection request accepted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to respond",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to respond to this request")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid status or request already responded",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This request has already been responded to")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function respond(Request $request, UserConnection $connection): JsonResponse
    {
        // Check if user is the addressee
        if ($connection->addressee_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to respond to this request'
            ], 403);
        }

        // Check if request is still pending
        if ($connection->status !== 'pending') {
            return response()->json([
                'message' => 'This request has already been responded to'
            ], 422);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:accept,decline,block']
        ]);

        switch ($validated['action']) {
            case 'accept':
                $connection->accept();
                $message = 'Connection request accepted';
                break;
            case 'decline':
                $connection->decline();
                $message = 'Connection request declined';
                break;
            case 'block':
                $connection->block();
                $message = 'User blocked';
                break;
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'status' => $connection->status,
                'responded_at' => $connection->responded_at,
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/user-connections/{connection}",
     *     summary="Remove a connection",
     *     description="Delete an existing user connection",
     *     operationId="removeConnection",
     *     tags={"User Connections"},
     *     @OA\Parameter(
     *         name="connection",
     *         in="path",
     *         description="Connection ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connection removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connection removed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to remove connection",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to remove this connection")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Connection not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connection not found")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function destroy(Request $request, UserConnection $connection): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is part of this connection
        if ($connection->requester_id !== $user->id && $connection->addressee_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to remove this connection'
            ], 403);
        }

        $connection->delete();

        return response()->json([
            'message' => 'Connection removed successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-connections/search",
     *     summary="Search for users to connect with",
     *     description="Search for users by name, institution, or department to send connection requests",
     *     operationId="searchUsersToConnect",
     *     tags={"User Connections"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query (name or other fields)",
     *         required=false,
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
     *         description="Users found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Users found"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="institution", type="string"),
     *                     @OA\Property(property="department", type="string"),
     *                     @OA\Property(property="connection_status", type="string", enum={"none", "pending", "accepted", "declined"})
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'min:2'],
            'institution' => ['nullable', 'string'],
            'department' => ['nullable', 'string'],
        ]);

        $currentUser = $request->user();
        
        $query = User::where('id', '!=', $currentUser->id);

        if (!empty($validated['query'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', '%' . $validated['query'] . '%')
                  ->orWhere('institution', 'like', '%' . $validated['query'] . '%')
                  ->orWhere('department', 'like', '%' . $validated['query'] . '%');
            });
        }

        if (!empty($validated['institution'])) {
            $query->where('institution', 'like', '%' . $validated['institution'] . '%');
        }

        if (!empty($validated['department'])) {
            $query->where('department', 'like', '%' . $validated['department'] . '%');
        }

        $users = $query->select('id', 'name', 'institution', 'department', 'position')
                      ->limit(20)
                      ->get()
                      ->map(function ($user) use ($currentUser) {
                          $connectionStatus = $currentUser->getConnectionStatusWith($user);
                          
                          return [
                              'id' => $user->id,
                              'name' => $user->name,
                              'institution' => $user->institution,
                              'department' => $user->department,
                              'position' => $user->position,
                              'connection_status' => $connectionStatus,
                          ];
                      });

        return response()->json([
            'message' => 'Users found',
            'data' => $users
        ]);
    }
}
