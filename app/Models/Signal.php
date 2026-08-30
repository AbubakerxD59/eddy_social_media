<?php

namespace App\Models;

use App\Enums\SignalType;
use Database\Factories\SignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $parent_id
 * @property int $user_id
 * @property SignalType $type
 * @property string|null $body
 * @property string|null $link_url
 * @property string|null $link_title
 * @property string|null $link_description
 * @property string|null $link_image
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $likes_count
 * @property int|null $replies_count
 * @property bool|int|null $liked
 * @property bool|int|null $saved
 * @property bool|int|null $reported
 */
#[Fillable(['user_id', 'parent_id', 'type', 'body', 'link_url', 'link_title', 'link_description', 'link_image'])]
#[Hidden(['id'])]
class Signal extends Model
{
    /** @use HasFactory<SignalFactory> */
    use HasFactory;

    public const PUBLIC_ID_LENGTH = 12;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SignalType::class,
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
     * @param  Builder<Signal>  $query
     * @return Builder<Signal>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Signal, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Signal::class, 'parent_id');
    }

    /**
     * @return HasMany<Signal, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Signal::class, 'parent_id')->latest();
    }

    /**
     * @return HasMany<SignalMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(SignalMedia::class)->orderBy('position');
    }

    /**
     * @return HasMany<SignalLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(SignalLike::class);
    }

    /**
     * @return HasMany<SignalSave, $this>
     */
    public function saves(): HasMany
    {
        return $this->hasMany(SignalSave::class);
    }

    /**
     * @return HasMany<SignalReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(SignalReport::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Signal $signal): void {
            if (filled($signal->public_id)) {
                return;
            }

            $signal->public_id = static::generatePublicId();
        });
    }
}
