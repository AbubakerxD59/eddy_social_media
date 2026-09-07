<?php

namespace App\Models;

use App\Enums\MediaType;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property MediaType $kind
 * @property string $path
 * @property string|null $mime_type
 * @property string|null $caption
 * @property Carbon $expires_at
 * @property string $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'kind', 'path', 'mime_type', 'caption', 'expires_at'])]
#[Hidden(['id'])]
class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory;

    public const PUBLIC_ID_LENGTH = 12;

    public const LIFETIME_HOURS = 24;

    public const MAX_ACTIVE_PER_USER = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MediaType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function generatePublicId(): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do {
            $id = '';

            for ($i = 0; $i < self::PUBLIC_ID_LENGTH; $i++) {
                $id .= $alphabet[random_int(0, 61)];
            }
        } while (static::query()->where('public_id', $id)->exists());

        return $id;
    }

    /**
     * @param  Builder<Story>  $query
     * @return Builder<Story>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<Story>  $query
     * @return Builder<Story>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->path));
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFeedArray(User $viewer): array
    {
        return [
            'id' => $this->public_id,
            'kind' => $this->kind->value,
            'url' => $this->url,
            'caption' => $this->caption,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'can_delete' => $this->user_id === $viewer->id,
        ];
    }

    /**
     * @param  list<int>  $mutedAuthorIds
     * @return list<array{user: array<string, mixed>, items: list<array<string, mixed>>}>
     */
    public static function groupedForFeed(User $viewer, array $mutedAuthorIds = []): array
    {
        static::pruneExpired();

        $stories = static::query()
            ->active()
            ->with('user')
            ->when($mutedAuthorIds !== [], fn (Builder $query) => $query->whereNotIn('user_id', $mutedAuthorIds))
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id');

        /** @var Collection<int, array{user: array<string, mixed>, items: list<array<string, mixed>>, latest_at: Carbon|null}> $groups */
        $groups = $stories->map(function (Collection $items) use ($viewer): array {
            /** @var self $first */
            $first = $items->first();

            return [
                'user' => $first->user->toPublicArray(),
                'items' => $items
                    ->map(fn (self $story): array => $story->toFeedArray($viewer))
                    ->values()
                    ->all(),
                'latest_at' => $items->max('created_at'),
            ];
        });

        $mine = $groups->first(fn (array $group): bool => $group['user']['id'] === $viewer->id);

        $others = $groups
            ->reject(fn (array $group): bool => $group['user']['id'] === $viewer->id)
            ->sortByDesc(fn (array $group): mixed => $group['latest_at'])
            ->values();

        return collect($mine ? [$mine] : [])
            ->concat($others)
            ->map(fn (array $group): array => [
                'user' => $group['user'],
                'items' => $group['items'],
            ])
            ->take(24)
            ->values()
            ->all();
    }

    public static function pruneExpired(): void
    {
        static::query()
            ->expired()
            ->limit(100)
            ->get()
            ->each(fn (self $story) => $story->delete());
    }

    protected static function booted(): void
    {
        static::creating(function (Story $story): void {
            if (blank($story->public_id)) {
                $story->public_id = static::generatePublicId();
            }

            if ($story->expires_at === null) {
                $story->expires_at = now()->addHours(self::LIFETIME_HOURS);
            }
        });

        static::deleting(function (Story $story): void {
            Storage::disk('public')->delete($story->path);
        });
    }
}
