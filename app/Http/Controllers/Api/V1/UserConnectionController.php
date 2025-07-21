<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserConnectionController extends Controller
{
    /**
     * Get user's connections
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
     * Get pending connection requests
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
     * Send a connection request
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
     * Respond to a connection request
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
     * Remove a connection
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
     * Search for users to connect with
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
