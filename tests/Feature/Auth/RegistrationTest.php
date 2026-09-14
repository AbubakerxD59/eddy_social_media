<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\CountryCallingCodes;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_requires_the_new_profile_fields()
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'type' => 'business',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'full_name',
                'last_name',
                'phone_country_code',
                'phone_number',
                'gender',
                'date_of_birth',
                'business_name',
                'fiscal_year',
                'full_time_employees',
            ]);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->has('countryCodes')
            ->has('accountType')
            ->where('countryCodes', function ($countryCodes) {
                $pakistan = collect($countryCodes)->firstWhere('name', 'Pakistan');
                $unitedStates = collect($countryCodes)->firstWhere('name', 'United States');

                return $pakistan['code'] === '+92'
                    && $pakistan['label'] === 'Pakistan (+92)'
                    && $unitedStates['code'] === '+1'
                    && $unitedStates['label'] === 'United States (+1)';
            }),
        );

        $this->assertContains('+92', CountryCallingCodes::codes());
        $this->assertSame(
            'United Kingdom (+44)',
            collect(CountryCallingCodes::options())->firstWhere('name', 'United Kingdom')['label'],
        );
    }

    public function test_new_users_can_register()
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'type' => 'business',
            'full_name' => 'Ada',
            'last_name' => 'Founder',
            'email' => 'test@example.com',
            'phone_country_code' => '+1',
            'phone_number' => '2025550147',
            'gender' => 'female',
            'date_of_birth' => '1990-05-15',
            'business_name' => 'Eddy Labs',
            'fiscal_year' => now()->year - 1,
            'full_time_employees' => 12,
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'full_name' => 'Ada',
            'last_name' => 'Founder',
            'business_name' => 'Eddy Labs',
            'name' => 'Eddy Labs',
            'type' => 'business',
            'phone_country_code' => '+1',
            'phone_number' => '2025550147',
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_registration_requires_a_strong_password(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'type' => 'business',
            'full_name' => 'Ada',
            'last_name' => 'Founder',
            'email' => 'test@example.com',
            'phone_country_code' => '+1',
            'phone_number' => '2025550147',
            'gender' => 'female',
            'date_of_birth' => '1990-05-15',
            'business_name' => 'Eddy Labs',
            'fiscal_year' => now()->year - 1,
            'full_time_employees' => 12,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('password');
    }

    public function test_explorers_can_register_without_business_fields(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'type' => 'explorer',
            'full_name' => 'Sam',
            'last_name' => 'River',
            'email' => 'sam@example.com',
            'phone_country_code' => '+1',
            'phone_number' => '2025550199',
            'gender' => 'other',
            'date_of_birth' => '1992-03-20',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'sam@example.com',
            'name' => 'Sam River',
            'type' => 'explorer',
            'business_name' => null,
            'email_verified_at' => null,
        ]);
    }

    public function test_talent_can_register_with_a_talent_profile(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'type' => 'talent',
            'full_name' => 'Lee',
            'last_name' => 'Chen',
            'email' => 'lee@example.com',
            'phone_country_code' => '+1',
            'phone_number' => '2025550110',
            'gender' => 'male',
            'date_of_birth' => '1988-11-02',
            'talent_headline' => 'Product illustration',
            'talent_bio' => 'I design product worlds for SaaS teams.',
            'talent_skills' => 'Illustration, branding',
            'talent_hourly_rate' => 120,
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'lee@example.com',
            'name' => 'Lee Chen',
            'type' => 'talent',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('talent_profiles', [
            'headline' => 'Product illustration',
            'hourly_rate_cents' => 12000,
        ]);
    }
}
