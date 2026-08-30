<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
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
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'full_name' => 'Ada',
            'last_name' => 'Founder',
            'business_name' => 'Eddy Labs',
            'name' => 'Eddy Labs',
            'phone_country_code' => '+1',
            'phone_number' => '2025550147',
        ]);
    }

    public function test_registration_requires_a_strong_password(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
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
}
