<?php

use App\Models\MentorProfile;
use App\Models\Signal;
use App\Models\User;

test('a public profile shows the users signals', function () {
    $user = User::factory()->create(['username' => 'ada']);
    Signal::factory()->for($user)->create(['body' => 'Building in public.']);

    $this->actingAs(User::factory()->create())
        ->get(route('profiles.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->where('profile.username', 'ada')
            ->where('profile.is_own', false)
            ->has('signals.data', 1));
});

test('users can list themselves as mentors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('mentors.store'), [
            'headline' => 'B2B sales systems',
            'bio' => 'I help first-time founders close their first ten customers.',
            'hourly_rate_cents' => 20000,
        ])
        ->assertRedirect(route('mentors.index'));

    $this->assertDatabaseHas('mentor_profiles', [
        'user_id' => $user->id,
        'is_accepting_bookings' => true,
        'headline' => 'B2B sales systems',
    ]);

    $this->actingAs($user)
        ->get(route('mentors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mentors/Index')
            ->where('isMentor', true)
            ->has('mentors.data', 1));
});

test('mentor profiles appear on the public profile', function () {
    $user = User::factory()->create(['username' => 'mentorann']);

    MentorProfile::query()->create([
        'user_id' => $user->id,
        'headline' => 'Product ops',
        'is_accepting_bookings' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profiles.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('profile.is_mentor', true)
            ->where('profile.is_own', true));
});
