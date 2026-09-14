<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SignalType;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $full_name
 * @property string|null $last_name
 * @property string|null $business_name
 * @property string $username
 * @property UserType $type
 * @property string $email
 * @property string|null $phone_country_code
 * @property string|null $phone_number
 * @property Gender|string|null $gender
 * @property Carbon|null $date_of_birth
 * @property int|null $fiscal_year
 * @property int|null $full_time_employees
 * @property string|null $headline
 * @property string|null $bio
 * @property string|null $website
 * @property string|null $avatar_path
 * @property string|null $cover_path
 * @property string|null $avatar_url
 * @property string|null $cover_url
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $location_updated_at
 * @property string|null $location_label
 * @property bool $location_manual
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'full_name',
    'last_name',
    'business_name',
    'username',
    'type',
    'email',
    'phone_country_code',
    'phone_number',
    'gender',
    'date_of_birth',
    'fiscal_year',
    'full_time_employees',
    'password',
    'headline',
    'bio',
    'website',
    'avatar_path',
    'cover_path',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'latitude', 'longitude', 'location_updated_at', 'location_label', 'location_manual'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'gender' => Gender::class,
            'type' => UserType::class,
            'date_of_birth' => 'date',
            'fiscal_year' => 'integer',
            'full_time_employees' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_updated_at' => 'datetime',
            'location_manual' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Signal, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }

    /**
     * @return HasMany<SignalUpload, $this>
     */
    public function signalUploads(): HasMany
    {
        return $this->hasMany(SignalUpload::class);
    }

    /**
     * @return HasMany<Story, $this>
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /**
     * @return HasMany<UserMute, $this>
     */
    public function mutes(): HasMany
    {
        return $this->hasMany(UserMute::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * @return HasOne<MentorProfile, $this>
     */
    public function mentorProfile(): HasOne
    {
        return $this->hasOne(MentorProfile::class);
    }

    /**
     * @return HasOne<TalentProfile, $this>
     */
    public function talentProfile(): HasOne
    {
        return $this->hasOne(TalentProfile::class);
    }

    public function accountType(): UserType
    {
        return $this->type ?? UserType::Business;
    }

    public function isBusiness(): bool
    {
        return $this->accountType() === UserType::Business;
    }

    public function isTalent(): bool
    {
        return $this->accountType() === UserType::Talent;
    }

    public function isExplorer(): bool
    {
        return $this->accountType() === UserType::Explorer;
    }

    public function canCompose(SignalType $type, bool $isReply = false): bool
    {
        return $this->accountType()->canCompose($type, $isReply);
    }

    /**
     * @return list<string>
     */
    public function composeTypes(): array
    {
        return array_map(
            fn (SignalType $type) => $type->value,
            $this->accountType()->composeTypes(),
        );
    }

    public function displayName(): string
    {
        if ($this->isBusiness()) {
            return $this->business_name ?: $this->name;
        }

        $personal = trim(implode(' ', array_filter([$this->full_name, $this->last_name])));

        return $personal !== '' ? $personal : $this->name;
    }

    public static function uniqueUsername(string $source): string
    {
        $base = Str::lower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $source));
        $base = trim($base, '_') ?: 'user';
        $base = Str::limit($base, 24, '');

        if (strlen($base) < 3) {
            $base .= Str::lower(Str::random(3 - strlen($base)));
        }

        $username = $base;
        $suffix = 0;

        while (self::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = Str::limit($base, 24, '').$suffix;
        }

        return $username;
    }

    /**
     * @param  array{headline: string, bio: string, skills?: list<string>|string|null, hourly_rate_cents?: int|null}  $data
     */
    public function becomeTalent(array $data): TalentProfile
    {
        abort_unless($this->isExplorer() || $this->isTalent(), 403);

        $this->forceFill(['type' => UserType::Talent])->save();

        if (blank($this->headline) && filled($data['headline'] ?? null)) {
            $this->forceFill(['headline' => $data['headline']])->save();
        }

        if (blank($this->bio) && filled($data['bio'] ?? null)) {
            $this->forceFill(['bio' => $data['bio']])->save();
        }

        return $this->talentProfile()->updateOrCreate(
            ['user_id' => $this->id],
            [
                'headline' => $data['headline'],
                'bio' => $data['bio'],
                'skills' => $data['skills'] ?? [],
                'hourly_rate_cents' => $data['hourly_rate_cents'] ?? null,
                'is_available' => true,
            ],
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->avatar_path) {
                return null;
            }

            return Storage::disk('public')->url($this->avatar_path);
        });
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->cover_path) {
                return null;
            }

            return Storage::disk('public')->url($this->cover_path);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertia(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'full_name' => $this->full_name,
            'last_name' => $this->last_name,
            'business_name' => $this->business_name,
            'username' => $this->username,
            'type' => $this->accountType()->value,
            'compose_types' => $this->composeTypes(),
            'email' => $this->email,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'website' => $this->website,
            'avatar' => $this->avatar_url,
            'cover' => $this->cover_url,
            'email_verified_at' => $this->email_verified_at,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'username' => $this->username,
            'type' => $this->accountType()->value,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'website' => $this->website,
            'avatar' => $this->avatar_url,
            'cover' => $this->cover_url,
            'created_at' => $this->created_at,
        ];
    }
}
