<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $addressee_id
 * @property ConnectionStatus $status
 */
#[Fillable(['requester_id', 'addressee_id', 'status'])]
class Connection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public static function between(int $firstId, int $secondId): ?self
    {
        return self::query()
            ->where(function ($query) use ($firstId, $secondId): void {
                $query->where('requester_id', $firstId)->where('addressee_id', $secondId);
            })
            ->orWhere(function ($query) use ($firstId, $secondId): void {
                $query->where('requester_id', $secondId)->where('addressee_id', $firstId);
            })
            ->latest('id')
            ->first();
    }

    public function isPending(): bool
    {
        return $this->status === ConnectionStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === ConnectionStatus::Accepted;
    }

    /**
     * @return 'none'|'pending_outgoing'|'pending_incoming'|'accepted'
     */
    public static function statusFor(?int $viewerId, int $otherId): string
    {
        if ($viewerId === null || $viewerId === $otherId) {
            return 'none';
        }

        $connection = self::between($viewerId, $otherId);

        if ($connection === null || $connection->status === ConnectionStatus::Rejected) {
            return 'none';
        }

        if ($connection->isAccepted()) {
            return 'accepted';
        }

        return $connection->requester_id === $viewerId
            ? 'pending_outgoing'
            : 'pending_incoming';
    }
}
