<?php

namespace App\Support;

use App\Models\Connection;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserNotification;

class Notify
{
    /**
     * @param  array{type: string, title: string, body?: string|null, url: string, actor_id?: int|null, signal_id?: int|null, connection_id?: int|null, conversation_id?: int|null}  $payload
     */
    public static function to(User $recipient, array $payload): UserNotification
    {
        return UserNotification::query()->create([
            'user_id' => $recipient->id,
            'actor_id' => $payload['actor_id'] ?? null,
            'signal_id' => $payload['signal_id'] ?? null,
            'connection_id' => $payload['connection_id'] ?? null,
            'conversation_id' => $payload['conversation_id'] ?? null,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'url' => $payload['url'],
            'read_at' => null,
        ]);
    }

    public static function connectionRequest(User $recipient, User $actor, Connection $connection): UserNotification
    {
        return self::to($recipient, [
            'type' => 'connection_request',
            'title' => $actor->displayName().' sent you a connection request',
            'body' => $actor->headline ?: '@'.$actor->username,
            'url' => route('profiles.show', $actor, absolute: false),
            'actor_id' => $actor->id,
            'connection_id' => $connection->id,
        ]);
    }

    public static function connectionAccepted(User $recipient, User $actor, Connection $connection): UserNotification
    {
        return self::to($recipient, [
            'type' => 'connection_accepted',
            'title' => $actor->displayName().' accepted your connection request',
            'body' => 'You are now connected.',
            'url' => route('profiles.show', $actor, absolute: false),
            'actor_id' => $actor->id,
            'connection_id' => $connection->id,
        ]);
    }

    public static function messageRequest(User $recipient, User $actor, Conversation $conversation): UserNotification
    {
        return self::to($recipient, [
            'type' => 'message_request',
            'title' => $actor->displayName().' sent you a message request',
            'body' => $conversation->last_message_preview,
            'url' => route('messages.show', $conversation, absolute: false),
            'actor_id' => $actor->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    public static function messageAccepted(User $recipient, User $actor, Conversation $conversation): UserNotification
    {
        return self::to($recipient, [
            'type' => 'message_accepted',
            'title' => $actor->displayName().' accepted your message request',
            'body' => 'You can keep chatting.',
            'url' => route('messages.show', $conversation, absolute: false),
            'actor_id' => $actor->id,
            'conversation_id' => $conversation->id,
        ]);
    }
}
