<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\User;
use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    public function card(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        return response()->json([
            'user' => $user->toPublicArray(),
            'is_own' => $viewer?->is($user) ?? false,
            'connection' => Connection::statusFor($viewer?->id, $user->id),
        ]);
    }

    public function store(Request $request, User $user, ConnectionService $connections): JsonResponse
    {
        $result = $connections->request($request->user(), $user);

        $message = match (true) {
            $result['just_accepted'] ?? false => 'You are now connected.',
            $result['status'] === 'accepted' => 'You are already connected.',
            $result['created'] => 'Connection request sent.',
            default => 'Connection request already sent.',
        };

        return response()->json([
            'message' => $message,
            'status' => $result['status'],
            'connection_id' => $result['connection']->id,
        ]);
    }

    public function accept(Request $request, Connection $connection, ConnectionService $connections): JsonResponse
    {
        $connections->accept($request->user(), $connection);

        return response()->json([
            'message' => 'Connection request accepted.',
            'status' => 'accepted',
        ]);
    }

    public function reject(Request $request, Connection $connection, ConnectionService $connections): JsonResponse
    {
        $connections->reject($request->user(), $connection);

        return response()->json([
            'message' => 'Connection request declined.',
            'status' => 'rejected',
        ]);
    }
}
