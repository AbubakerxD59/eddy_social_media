<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Models\User;
use App\Support\CountryCallingCodes;
use App\Support\TalentSkills;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'type' => ['required', Rule::enum(UserType::class)],
            'full_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $this->emailRules(),
            'phone_country_code' => ['required', 'string', Rule::in(CountryCallingCodes::codes())],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'business_name' => ['required_if:type,business', 'nullable', 'string', 'max:255'],
            'fiscal_year' => ['required_if:type,business', 'nullable', 'integer', 'min:1990', 'max:'.now()->year],
            'full_time_employees' => ['required_if:type,business', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'talent_headline' => ['required_if:type,talent', 'nullable', 'string', 'max:160'],
            'talent_bio' => ['required_if:type,talent', 'nullable', 'string', 'max:1200'],
            'talent_skills' => ['required_if:type,talent', 'nullable', 'string', 'max:2000'],
            'talent_hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'password' => $this->passwordRules(),
        ])->validate();

        $type = UserType::from((string) $input['type']);
        $fullName = trim((string) $input['full_name']);
        $lastName = trim((string) $input['last_name']);
        $personalName = trim($fullName.' '.$lastName);
        $businessName = $type === UserType::Business ? trim((string) $input['business_name']) : null;
        $displayName = $businessName ?: $personalName;

        return DB::transaction(function () use ($input, $type, $fullName, $lastName, $businessName, $displayName): User {
            $user = User::create([
                'name' => $displayName,
                'full_name' => $fullName,
                'last_name' => $lastName,
                'business_name' => $businessName,
                'username' => User::uniqueUsername($displayName),
                'type' => $type,
                'email' => $input['email'],
                'phone_country_code' => $input['phone_country_code'],
                'phone_number' => $input['phone_number'],
                'gender' => $input['gender'],
                'date_of_birth' => $input['date_of_birth'],
                'fiscal_year' => $type === UserType::Business ? $input['fiscal_year'] : null,
                'full_time_employees' => $type === UserType::Business ? $input['full_time_employees'] : null,
                'headline' => $type === UserType::Talent ? ($input['talent_headline'] ?? null) : null,
                'bio' => $type === UserType::Talent ? ($input['talent_bio'] ?? null) : null,
                'password' => $input['password'],
            ]);

            if ($type === UserType::Talent) {
                $user->becomeTalent([
                    'headline' => (string) $input['talent_headline'],
                    'bio' => (string) $input['talent_bio'],
                    'skills' => TalentSkills::fromInput($input['talent_skills'] ?? ''),
                    'hourly_rate_cents' => TalentSkills::centsFromRate($input['talent_hourly_rate'] ?? null),
                ]);
            }

            return $user;
        });
    }
}
