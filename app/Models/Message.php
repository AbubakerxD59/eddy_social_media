<?php

namespace App\Models;

use App\Enums\MessageKind;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $public_id
 * @property int $conversation_id
 * @property int $user_id
 * @property string|null $group_id
 * @property int|null $reply_to_id
 * @property MessageKind $kind
 * @property string|null $body
 * @property string|null $path
 * @property string|null $mime_type
 * @property string|null $original_name
 * @property int|null $size
 * @property CarbonInterface|null $edited_at
 * @property CarbonInterface|null $deleted_at
 * @property string|null $url
 */
#[Fillable(['conversation_id', 'user_id', 'group_id', 'reply_to_id', 'kind', 'body', 'path', 'mime_type', 'original_name', 'size', 'edited_at'])]
#[Hidden(['id'])]
class Message extends Model
{
    use HasPublicId;
    use SoftDeletes;

    public const DELETE_WINDOW_MINUTES = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
            'size' => 'integer',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withTrashed();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id && ! $this->trashed();
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($this->user_id !== $user->id || $this->trashed() || $this->created_at === null) {
            return false;
        }

        return $this->created_at->gt(now()->subMinutes(self::DELETE_WINDOW_MINUTES));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->trashed() || ! $this->path) {
                return null;
            }

            return Storage::disk('public')->url($this->path);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFeedArray(?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $deleted = $this->trashed();

        return [
            'id' => $this->public_id,
            'user_id' => $this->user_id,
            'group_id' => $this->group_id,
            'kind' => $this->kind->value,
            'body' => $deleted ? null : $this->body,
            'url' => $deleted ? null : $this->url,
            'mime_type' => $deleted ? null : $this->mime_type,
            'original_name' => $deleted ? null : $this->original_name,
            'size' => $deleted ? null : $this->size,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $deleted ? null : $this->edited_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can_edit' => $viewer instanceof User && $this->canBeEditedBy($viewer),
            'can_delete' => $viewer instanceof User && $this->canBeDeletedBy($viewer),
            'reply_to' => $this->replyTo?->toQuoteArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuoteArray(): array
    {
        $deleted = $this->trashed();
        $showThumb = ! $deleted && in_array($this->kind, [MessageKind::Image, MessageKind::Video], true);

        return [
            'id' => $this->public_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->displayName(),
            'kind' => $this->kind->value,
            'body' => $deleted ? null : $this->body,
            'url' => $showThumb ? $this->url : null,
            'original_name' => $deleted ? null : $this->original_name,
            'deleted' => $deleted,
        ];
    }
}
