<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_id
 * @property int|null $signal_id
 * @property int|null $connection_id
 * @property int|null $conversation_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string $url
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'actor_id', 'signal_id', 'connection_id', 'conversation_id', 'type', 'title', 'body', 'url', 'read_at'])]
class UserNotification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Signal, $this>
     */
    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function peerConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFeedArray(): array
    {
        $connection = $this->peerConnection;
        $conversation = $this->conversation;
        $canRespondConnection = $this->type === 'connection_request'
            && $connection instanceof Connection
            && $connection->isPending()
            && $connection->addressee_id === $this->user_id;
        $canRespondMessage = $this->type === 'message_request'
            && $conversation instanceof Conversation
            && $conversation->isPending()
            && $conversation->initiator_id !== $this->user_id
            && $conversation->last_message_at !== null
            && in_array($this->user_id, [$conversation->user_low_id, $conversation->user_high_id], true);

        $canRespond = $canRespondConnection || $canRespondMessage;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'actor' => $this->actor?->toPublicArray(),
            'connection_id' => $this->connection_id,
            'conversation_id' => $this->conversation?->public_id,
            'can_accept' => $canRespond,
            'can_reject' => $canRespond,
        ];
    }
}
