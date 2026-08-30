<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\UserFactory;
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
 * @property string|null $avatar_url
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
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
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
            'date_of_birth' => 'date',
            'fiscal_year' => 'integer',
            'full_time_employees' => 'integer',
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
     * @return HasMany<UserMute, $this>
     */
    public function mutes(): HasMany
    {
        return $this->hasMany(UserMute::class);
    }

    /**
     * @return HasOne<MentorProfile, $this>
     */
    public function mentorProfile(): HasOne
    {
        return $this->hasOne(MentorProfile::class);
    }

    public function displayName(): string
    {
        return $this->business_name ?: $this->name;
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
            'email' => $this->email,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'website' => $this->website,
            'avatar' => $this->avatar_url,
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
            'headline' => $this->headline,
            'bio' => $this->bio,
            'website' => $this->website,
            'avatar' => $this->avatar_url,
            'created_at' => $this->created_at,
        ];
    }
}
