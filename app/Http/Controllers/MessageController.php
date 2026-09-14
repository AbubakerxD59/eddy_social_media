<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->page($request->user(), null);
    }

    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->boolean('all') ? 80 : 10;

        return response()->json([
            'conversations' => Conversation::recentInboxFor($user, $limit),
            'unread_count' => Conversation::unreadCountFor($user),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), 403);

        $conversation->markRead($user);

        return $this->page($user, $conversation->fresh());
    }

    public function with(Request $request, User $user, ConversationService $conversations): RedirectResponse
    {
        $conversation = $conversations->open($request->user(), $user);

        return redirect()->route('messages.show', $conversation);
    }

    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        ConversationService $conversations,
    ): JsonResponse {
        $messages = $conversations->send(
            $request->user(),
            $conversation,
            $request->string('body')->toString(),
            $request->uploadedFiles(),
            $request->filled('reply_to_id') ? $request->string('reply_to_id')->toString() : null,
        );

        $payload = array_map(fn (Message $message): array => $message->toFeedArray($request->user()), $messages);

        return response()->json([
            'messages' => $payload,
            'message' => $payload[0] ?? null,
            'conversation' => $conversation->fresh()?->toInboxArray($request->user()),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), 403);

        $afterId = 0;
        $afterKey = $request->string('after')->toString();

        if ($afterKey !== '') {
            $after = Message::withTrashed()
                ->where('conversation_id', $conversation->id)
                ->where('public_id', $afterKey)
                ->first();
            $afterId = $after?->id ?? 0;
        }

        if ($afterId > 0) {
            $items = $conversation->messages()
                ->with(['replyTo.user'])
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit(100)
                ->get();
        } else {
            $items = $conversation->messages()
                ->withTrashed()
                ->with(['replyTo.user'])
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values();
        }

        $updates = collect();
        $syncedAt = $request->query('synced_at');

        if (is_string($syncedAt) && $syncedAt !== '' && $afterId > 0) {
            try {
                $since = Carbon::parse($syncedAt);
                $updates = $conversation->messages()
                    ->withTrashed()
                    ->with(['replyTo.user'])
                    ->where('id', '<=', $afterId)
                    ->where(function ($query) use ($since): void {
                        $query->where('updated_at', '>', $since)
                            ->orWhere('edited_at', '>', $since)
                            ->orWhere('deleted_at', '>', $since);
                    })
                    ->orderBy('id')
                    ->limit(100)
                    ->get();
            } catch (\Throwable) {
                $updates = collect();
            }
        }

        $conversation->markRead($user);

        return response()->json([
            'messages' => $items->map(fn (Message $message) => $message->toFeedArray($user))->values()->all(),
            'updates' => $updates->map(fn (Message $message) => $message->toFeedArray($user))->values()->all(),
            'synced_at' => now()->toIso8601String(),
            'conversation' => $conversation->fresh()?->toInboxArray($user),
            'unread_count' => Conversation::unreadCountFor($user),
        ]);
    }

    public function update(
        UpdateMessageRequest $request,
        Conversation $conversation,
        Message $message,
        ConversationService $conversations,
    ): JsonResponse {
        $payload = $conversations->edit(
            $request->user(),
            $conversation,
            $message,
            $request->string('body')->toString(),
        );

        return response()->json([
            'messages' => $payload,
            'conversation' => $conversation->fresh()?->toInboxArray($request->user()),
        ]);
    }

    public function destroy(
        Request $request,
        Conversation $conversation,
        Message $message,
        ConversationService $conversations,
    ): JsonResponse {
        abort_unless($conversation->isParticipant($request->user()), 403);

        $payload = $conversations->remove($request->user(), $conversation, $message);

        return response()->json([
            'messages' => $payload,
            'conversation' => $conversation->fresh()?->toInboxArray($request->user()),
        ]);
    }

    public function accept(Request $request, Conversation $conversation, ConversationService $conversations): JsonResponse
    {
        $conversations->accept($request->user(), $conversation);

        return response()->json([
            'message' => 'Message request accepted.',
            'conversation' => $conversation->fresh()?->toInboxArray($request->user()),
        ]);
    }

    public function reject(Request $request, Conversation $conversation, ConversationService $conversations): JsonResponse
    {
        $conversations->reject($request->user(), $conversation);

        return response()->json([
            'message' => 'Message request declined.',
            'conversation' => $conversation->fresh()?->toInboxArray($request->user()),
        ]);
    }

    private function page(User $user, ?Conversation $conversation): Response
    {
        $inbox = Conversation::query()
            ->forUser($user)
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->limit(80)
            ->get();

        $peerIds = $inbox
            ->toBase()
            ->map(fn (Conversation $item) => $item->otherUserId($user))
            ->when($conversation !== null, fn ($ids) => $ids->push($conversation->otherUserId($user)))
            ->unique()
            ->values();

        $peers = User::query()->whereIn('id', $peerIds)->get()->keyBy('id');

        $messages = [];

        if ($conversation !== null) {
            $messages = $conversation->messages()
                ->withTrashed()
                ->with(['replyTo.user'])
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (Message $message) => $message->toFeedArray($user))
                ->all();
        }

        return Inertia::render('Messages/Index', [
            'conversations' => $inbox
                ->map(fn (Conversation $item) => $item->toInboxArray($user, $peers->get($item->otherUserId($user))))
                ->values()
                ->all(),
            'conversation' => $conversation?->toInboxArray($user, $peers->get($conversation->otherUserId($user))),
            'messages' => $messages,
            'unread_count' => Conversation::unreadCountFor($user),
        ]);
    }
}
