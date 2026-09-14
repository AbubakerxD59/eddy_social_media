<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_low_id
 * @property int $user_high_id
 * @property int $initiator_id
 * @property ConversationStatus $status
 * @property string|null $last_message_preview
 * @property CarbonInterface|null $last_message_at
 * @property CarbonInterface|null $low_last_read_at
 * @property CarbonInterface|null $high_last_read_at
 */
#[Fillable([
    'user_low_id',
    'user_high_id',
    'initiator_id',
    'status',
    'last_message_preview',
    'last_message_at',
    'low_last_read_at',
    'high_last_read_at',
])]
#[Hidden(['id'])]
class Conversation extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'low_last_read_at' => 'datetime',
            'high_last_read_at' => 'datetime',
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function pairIds(int $firstId, int $secondId): array
    {
        return $firstId < $secondId
            ? [$firstId, $secondId]
            : [$secondId, $firstId];
    }

    public static function between(int $firstId, int $secondId): ?self
    {
        [$low, $high] = self::pairIds($firstId, $secondId);

        return self::query()
            ->where('user_low_id', $low)
            ->where('user_high_id', $high)
            ->first();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $pair) use ($user): void {
            $pair->where('user_low_id', $user->id)->orWhere('user_high_id', $user->id);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function isParticipant(User $user): bool
    {
        return in_array($user->id, [$this->user_low_id, $this->user_high_id], true);
    }

    public function otherUserId(User $user): int
    {
        return $this->user_low_id === $user->id ? $this->user_high_id : $this->user_low_id;
    }

    public function isPending(): bool
    {
        return $this->status === ConversationStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === ConversationStatus::Accepted;
    }

    public function isRejected(): bool
    {
        return $this->status === ConversationStatus::Rejected;
    }

    public function canSend(User $user): bool
    {
        if (! $this->isParticipant($user) || $this->isRejected()) {
            return false;
        }

        if ($this->isAccepted()) {
            return true;
        }

        return $this->initiator_id === $user->id;
    }

    public function canRespond(User $user): bool
    {
        return $this->isPending()
            && $this->isParticipant($user)
            && $this->initiator_id !== $user->id
            && $this->last_message_at !== null;
    }

    public function markRead(User $user): void
    {
        $column = $this->user_low_id === $user->id ? 'low_last_read_at' : 'high_last_read_at';

        $this->forceFill([$column => now()])->save();
    }

    public function lastReadAt(User $user): ?CarbonInterface
    {
        return $this->user_low_id === $user->id ? $this->low_last_read_at : $this->high_last_read_at;
    }

    public function isUnreadFor(User $user): bool
    {
        if ($this->last_message_at === null) {
            return false;
        }

        $readAt = $this->lastReadAt($user);

        return $readAt === null || $this->last_message_at->greaterThan($readAt);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentInboxFor(User $user, int $limit = 10): array
    {
        $inbox = self::query()
            ->forUser($user)
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();

        $peerIds = $inbox
            ->toBase()
            ->map(fn (self $item) => $item->otherUserId($user))
            ->unique()
            ->values();

        $peers = User::query()->whereIn('id', $peerIds)->get()->keyBy('id');

        return $inbox
            ->map(fn (self $item) => $item->toInboxArray($user, $peers->get($item->otherUserId($user))))
            ->values()
            ->all();
    }

    public static function unreadCountFor(User $user): int
    {
        return (int) self::query()
            ->forUser($user)
            ->whereNotNull('last_message_at')
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $low) use ($user): void {
                    $low->where('user_low_id', $user->id)
                        ->where(function (Builder $unread): void {
                            $unread->whereNull('low_last_read_at')
                                ->orWhereColumn('last_message_at', '>', 'low_last_read_at');
                        });
                })->orWhere(function (Builder $high) use ($user): void {
                    $high->where('user_high_id', $user->id)
                        ->where(function (Builder $unread): void {
                            $unread->whereNull('high_last_read_at')
                                ->orWhereColumn('last_message_at', '>', 'high_last_read_at');
                        });
                });
            })
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function toInboxArray(User $viewer, ?User $peer = null): array
    {
        $peer ??= User::query()->find($this->otherUserId($viewer));

        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'peer' => $peer?->toPublicArray(),
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread' => $this->isUnreadFor($viewer),
            'can_send' => $this->canSend($viewer),
            'can_respond' => $this->canRespond($viewer),
            'is_initiator' => $this->initiator_id === $viewer->id,
        ];
    }
}
