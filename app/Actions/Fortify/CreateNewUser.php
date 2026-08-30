<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Gender;
use App\Models\User;
use App\Support\CountryCallingCodes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            'full_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $this->emailRules(),
            'phone_country_code' => ['required', 'string', Rule::in(CountryCallingCodes::codes())],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'business_name' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'integer', 'min:1990', 'max:'.now()->year],
            'full_time_employees' => ['required', 'integer', 'min:0', 'max:1000000'],
            'password' => $this->passwordRules(),
        ])->validate();

        $businessName = trim((string) $input['business_name']);

        return User::create([
            'name' => $businessName,
            'full_name' => $input['full_name'],
            'last_name' => $input['last_name'],
            'business_name' => $businessName,
            'username' => $this->uniqueUsername($businessName),
            'email' => $input['email'],
            'phone_country_code' => $input['phone_country_code'],
            'phone_number' => $input['phone_number'],
            'gender' => $input['gender'],
            'date_of_birth' => $input['date_of_birth'],
            'fiscal_year' => $input['fiscal_year'],
            'full_time_employees' => $input['full_time_employees'],
            'password' => $input['password'],
        ]);
    }

    private function uniqueUsername(string $businessName): string
    {
        $base = Str::lower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $businessName));
        $base = trim($base, '_') ?: 'user';
        $base = Str::limit($base, 24, '');

        if (strlen($base) < 3) {
            $base .= Str::lower(Str::random(3 - strlen($base)));
        }

        $username = $base;
        $suffix = 0;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = Str::limit($base, 24, '').$suffix;
        }

        return $username;
    }
}
