<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property MediaType $kind
 * @property string $path
 * @property string|null $mime_type
 * @property string $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'kind', 'path', 'mime_type'])]
#[Hidden(['id'])]
class SignalUpload extends Model
{
    public const PUBLIC_ID_LENGTH = 12;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MediaType::class,
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
     * @param  Builder<SignalUpload>  $query
     * @return Builder<SignalUpload>
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query->where('created_at', '<', now()->subDay());
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

    public function deleteFile(): void
    {
        Storage::disk('public')->delete($this->path);
    }

    protected static function booted(): void
    {
        static::creating(function (SignalUpload $upload): void {
            if (filled($upload->public_id)) {
                return;
            }

            $upload->public_id = static::generatePublicId();
        });

        static::deleting(function (SignalUpload $upload): void {
            $upload->deleteFile();
        });
    }
}
