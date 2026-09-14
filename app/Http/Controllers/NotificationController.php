<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\ConnectionService;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $afterId = $request->integer('after_id');

        $query = $user->notifications()->with(['actor', 'peerConnection', 'conversation']);

        if ($afterId > 0) {
            $notifications = $query
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit(50)
                ->get();
        } else {
            $limit = $request->boolean('all') ? 200 : 10;
            $notifications = $query->latest()->limit($limit)->get();
        }

        return response()->json([
            'unread_count' => $user->notifications()->unread()->count(),
            'notifications' => $notifications
                ->map(fn (UserNotification $notification) => $notification->toFeedArray())
                ->values()
                ->all(),
        ]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'unread_count' => $request->user()->notifications()->unread()->count(),
            'notification' => $notification->fresh(['actor', 'peerConnection', 'conversation'])?->toFeedArray(),
        ]);
    }

    public function accept(Request $request, UserNotification $notification, ConnectionService $connections, ConversationService $conversations): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->type === 'message_request') {
            abort_unless($notification->conversation_id !== null, 422);
            $notification->loadMissing('conversation');
            abort_unless($notification->conversation !== null, 422);

            $conversations->accept($request->user(), $notification->conversation);

            return response()->json([
                'unread_count' => $request->user()->notifications()->unread()->count(),
                'notification' => $notification->fresh(['actor', 'peerConnection', 'conversation'])?->toFeedArray(),
                'message' => 'Message request accepted.',
            ]);
        }

        abort_unless($notification->type === 'connection_request', 422);
        abort_unless($notification->connection_id !== null, 422);

        $notification->loadMissing('peerConnection');
        abort_unless($notification->peerConnection !== null, 422);

        $connections->accept($request->user(), $notification->peerConnection);

        return response()->json([
            'unread_count' => $request->user()->notifications()->unread()->count(),
            'notification' => $notification->fresh(['actor', 'peerConnection', 'conversation'])?->toFeedArray(),
            'message' => 'Connection request accepted.',
        ]);
    }

    public function reject(Request $request, UserNotification $notification, ConnectionService $connections, ConversationService $conversations): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->type === 'message_request') {
            abort_unless($notification->conversation_id !== null, 422);
            $notification->loadMissing('conversation');
            abort_unless($notification->conversation !== null, 422);

            $conversations->reject($request->user(), $notification->conversation);

            return response()->json([
                'unread_count' => $request->user()->notifications()->unread()->count(),
                'notification' => $notification->fresh(['actor', 'peerConnection', 'conversation'])?->toFeedArray(),
                'message' => 'Message request declined.',
            ]);
        }

        abort_unless($notification->type === 'connection_request', 422);
        abort_unless($notification->connection_id !== null, 422);

        $notification->loadMissing('peerConnection');
        abort_unless($notification->peerConnection !== null, 422);

        $connections->reject($request->user(), $notification->peerConnection);

        return response()->json([
            'unread_count' => $request->user()->notifications()->unread()->count(),
            'notification' => $notification->fresh(['actor', 'peerConnection', 'conversation'])?->toFeedArray(),
            'message' => 'Connection request declined.',
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }
}
