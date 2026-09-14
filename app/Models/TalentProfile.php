<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $headline
 * @property string $bio
 * @property list<string>|null $skills
 * @property int|null $hourly_rate_cents
 * @property bool $is_available
 */
#[Fillable(['user_id', 'headline', 'bio', 'skills', 'hourly_rate_cents', 'is_available'])]
class TalentProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'hourly_rate_cents' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'skills' => $this->skills ?? [],
            'hourly_rate_cents' => $this->hourly_rate_cents,
            'is_available' => $this->is_available,
            'user' => $this->user->toPublicArray(),
        ];
    }
}
