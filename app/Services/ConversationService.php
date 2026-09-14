<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Models\Connection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Notify;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConversationService
{
    public function open(User $actor, User $target): Conversation
    {
        if ($actor->is($target)) {
            throw new AuthorizationException('You cannot message yourself.');
        }

        [$low, $high] = Conversation::pairIds($actor->id, $target->id);
        $conversation = Conversation::between($actor->id, $target->id);
        $connected = Connection::between($actor->id, $target->id)?->isAccepted() ?? false;
        $status = $connected ? ConversationStatus::Accepted : ConversationStatus::Pending;

        if ($conversation === null) {
            return Conversation::query()->create([
                'user_low_id' => $low,
                'user_high_id' => $high,
                'initiator_id' => $actor->id,
                'status' => $status,
            ]);
        }

        if ($conversation->isRejected()) {
            $conversation->forceFill([
                'initiator_id' => $actor->id,
                'status' => $status,
            ])->save();

            return $conversation;
        }

        if ($conversation->isPending() && $connected) {
            $conversation->forceFill(['status' => ConversationStatus::Accepted])->save();
        }

        return $conversation;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<Message>
     */
    public function send(User $actor, Conversation $conversation, ?string $body, array $files = [], ?string $replyToPublicId = null): array
    {
        $this->assertParticipant($actor, $conversation);

        if (! $conversation->canSend($actor)) {
            throw new HttpException(403, $this->blockedSendMessage($conversation, $actor));
        }

        $trimmed = trim((string) $body);
        $files = array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
        $reply = $this->resolveReply($conversation, $replyToPublicId);

        return DB::transaction(function () use ($actor, $conversation, $trimmed, $files, $reply): array {
            $groupId = count($files) > 1 ? (string) Str::uuid() : null;
            $created = [];
            $replyToId = $reply?->id;

            if ($files === []) {
                $created[] = Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $actor->id,
                    'group_id' => null,
                    'reply_to_id' => $replyToId,
                    'kind' => MessageKind::Text,
                    'body' => $trimmed !== '' ? $trimmed : null,
                    'path' => null,
                    'mime_type' => null,
                    'original_name' => null,
                    'size' => null,
                ]);
            } else {
                foreach ($files as $index => $file) {
                    $created[] = $this->storeAttachment(
                        $conversation,
                        $actor,
                        $file,
                        $groupId,
                        $index === 0 ? $trimmed : '',
                        $replyToId,
                    );
                }
            }

            $now = now();
            $readColumn = $conversation->user_low_id === $actor->id ? 'low_last_read_at' : 'high_last_read_at';

            $conversation->forceFill([
                'last_message_preview' => $this->previewFor($created, $trimmed),
                'last_message_at' => $now,
                $readColumn => $now,
            ])->save();

            $this->notifyRequestIfNeeded($actor, $conversation);

            foreach ($created as $message) {
                $message->setRelation('replyTo', $reply);
            }

            return $created;
        });
    }

    private function resolveReply(Conversation $conversation, ?string $replyToPublicId): ?Message
    {
        if (! filled($replyToPublicId)) {
            return null;
        }

        $reply = Message::query()->with('user')->where('public_id', $replyToPublicId)->first();

        if ($reply === null || $reply->conversation_id !== $conversation->id) {
            throw new HttpException(422, 'You can only reply to a message in this chat.');
        }

        return $reply;
    }

    private function storeAttachment(
        Conversation $conversation,
        User $actor,
        UploadedFile $file,
        ?string $groupId,
        string $trimmed,
        ?int $replyToId,
    ): Message {
        $mime = (string) $file->getMimeType();
        $kind = match (true) {
            str_starts_with($mime, 'image/') => MessageKind::Image,
            str_starts_with($mime, 'video/') => MessageKind::Video,
            default => MessageKind::File,
        };

        return Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $actor->id,
            'group_id' => $groupId,
            'reply_to_id' => $replyToId,
            'kind' => $kind,
            'body' => $trimmed !== '' ? $trimmed : null,
            'path' => $file->store('messages/'.$conversation->id, 'public'),
            'mime_type' => $mime,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * @param  list<Message>  $messages
     */
    private function previewFor(array $messages, string $trimmed): string
    {
        $kinds = array_map(fn (Message $message): MessageKind => $message->kind, $messages);
        $images = count(array_filter($kinds, fn (MessageKind $kind): bool => $kind === MessageKind::Image));
        $videos = count(array_filter($kinds, fn (MessageKind $kind): bool => $kind === MessageKind::Video));
        $files = count(array_filter($kinds, fn (MessageKind $kind): bool => $kind === MessageKind::File));
        $total = $images + $videos + $files;

        if ($total > 1) {
            if ($images === $total) {
                return $images.' photos';
            }

            if ($videos === $total) {
                return $videos.' videos';
            }

            if ($files === $total) {
                return $files.' files';
            }

            return $total.' files';
        }

        return match ($messages[0]->kind) {
            MessageKind::Image => 'Photo',
            MessageKind::Video => 'Video',
            MessageKind::File => $messages[0]->original_name ?: 'File',
            default => Str::limit($trimmed, 80),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function edit(User $actor, Conversation $conversation, Message $message, string $body): array
    {
        $this->assertParticipant($actor, $conversation);
        $this->assertMessageInConversation($conversation, $message);

        if (! $message->canBeEditedBy($actor)) {
            throw new AuthorizationException('You can only edit your own messages.');
        }

        $trimmed = trim($body);

        return DB::transaction(function () use ($conversation, $message, $trimmed): array {
            $group = $this->groupOf($message);
            $first = $group->first() ?? $message;
            $now = now();

            foreach ($group as $item) {
                $item->forceFill([
                    'body' => $item->is($first) ? ($trimmed !== '' ? $trimmed : null) : $item->body,
                    'edited_at' => $now,
                ])->save();
            }

            $this->refreshPreview($conversation);

            return $group
                ->fresh(['replyTo.user'])
                ->map(fn (Message $item): array => $item->toFeedArray())
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remove(User $actor, Conversation $conversation, Message $message): array
    {
        $this->assertParticipant($actor, $conversation);
        $this->assertMessageInConversation($conversation, $message);

        if (! $message->canBeDeletedBy($actor)) {
            throw new HttpException(403, 'You can only delete your own message within 10 minutes of sending it.');
        }

        return DB::transaction(function () use ($conversation, $message): array {
            $group = $this->groupOf($message);

            foreach ($group as $item) {
                $this->purgeAttachment($item);
                $item->delete();
            }

            $this->refreshPreview($conversation);

            $deleted = Message::withTrashed()
                ->with(['replyTo.user'])
                ->whereIn('id', $group->modelKeys())
                ->orderBy('id')
                ->get();

            return $deleted->map(fn (Message $item): array => $item->toFeedArray())->values()->all();
        });
    }

    public function refreshPreview(Conversation $conversation): void
    {
        $latest = $conversation->messages()->orderByDesc('id')->first();

        if ($latest === null) {
            $conversation->forceFill([
                'last_message_preview' => null,
            ])->save();

            return;
        }

        $group = $this->groupOf($latest);
        $caption = (string) ($group->first(fn (Message $item): bool => filled($item->body))?->body ?? '');

        $conversation->forceFill([
            'last_message_preview' => $this->previewFor($group->all(), $caption),
            'last_message_at' => $latest->created_at,
        ])->save();
    }

    /**
     * @return Collection<int, Message>
     */
    private function groupOf(Message $message)
    {
        if (! filled($message->group_id)) {
            return Message::query()->whereKey($message->id)->get();
        }

        return Message::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('group_id', $message->group_id)
            ->orderBy('id')
            ->get();
    }

    private function purgeAttachment(Message $message): void
    {
        if ($message->path) {
            Storage::disk('public')->delete($message->path);
        }

        $message->forceFill([
            'body' => null,
            'path' => null,
            'original_name' => null,
        ])->save();
    }

    private function assertMessageInConversation(Conversation $conversation, Message $message): void
    {
        if ($message->conversation_id !== $conversation->id) {
            throw new HttpException(404, 'Message not found.');
        }
    }

    public function accept(User $actor, Conversation $conversation): Conversation
    {
        $this->assertParticipant($actor, $conversation);

        if (! $conversation->canRespond($actor)) {
            throw new HttpException(422, 'This message request is no longer pending.');
        }

        $conversation->forceFill(['status' => ConversationStatus::Accepted])->save();

        $conversation->notifications()
            ->where('user_id', $actor->id)
            ->where('type', 'message_request')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $initiator = $conversation->initiator;

        if ($initiator !== null) {
            Notify::messageAccepted($initiator, $actor, $conversation);
        }

        return $conversation;
    }

    public function reject(User $actor, Conversation $conversation): Conversation
    {
        $this->assertParticipant($actor, $conversation);

        if (! $conversation->canRespond($actor)) {
            throw new HttpException(422, 'This message request is no longer pending.');
        }

        $conversation->forceFill(['status' => ConversationStatus::Rejected])->save();

        $conversation->notifications()
            ->where('user_id', $actor->id)
            ->where('type', 'message_request')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $conversation;
    }

    private function notifyRequestIfNeeded(User $actor, Conversation $conversation): void
    {
        if (! $conversation->isPending() || $conversation->initiator_id !== $actor->id) {
            return;
        }

        $alreadyNotified = UserNotification::query()
            ->where('conversation_id', $conversation->id)
            ->where('type', 'message_request')
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $recipient = User::query()->find($conversation->otherUserId($actor));

        if ($recipient !== null) {
            Notify::messageRequest($recipient, $actor, $conversation);
        }
    }

    private function blockedSendMessage(Conversation $conversation, User $actor): string
    {
        if ($conversation->isRejected()) {
            return 'This chat is closed.';
        }

        if ($conversation->isPending() && $conversation->initiator_id !== $actor->id) {
            return 'Accept this message request to reply.';
        }

        return 'You cannot send this message.';
    }

    private function assertParticipant(User $actor, Conversation $conversation): void
    {
        if (! $conversation->isParticipant($actor)) {
            throw new AuthorizationException('You are not part of this chat.');
        }
    }
}
