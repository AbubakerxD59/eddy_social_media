<?php

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;

test('explorers can convert themselves to talent', function () {
    $user = User::factory()->explorer()->create();

    $this->actingAs($user)
        ->post(route('talent.store'), [
            'headline' => 'Freelance brand design',
            'bio' => 'I help businesses ship visual identity.',
            'skills' => 'Branding, illustration',
            'hourly_rate' => 90,
        ])
        ->assertRedirect(route('mentors.index'));

    $user->refresh();

    expect($user->isTalent())->toBeTrue();

    $this->assertDatabaseHas('talent_profiles', [
        'user_id' => $user->id,
        'headline' => 'Freelance brand design',
        'hourly_rate_cents' => 9000,
    ]);
});

test('business accounts cannot convert to talent', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('talent.store'), [
            'headline' => 'Not allowed',
            'bio' => 'Businesses stay businesses.',
            'skills' => 'Ops',
        ])
        ->assertForbidden();

    expect($user->fresh()->isBusiness())->toBeTrue();
});

test('explorers can view needs but cannot post them', function () {
    $business = User::factory()->create();
    $need = Signal::factory()->need()->for($business)->create();
    $explorer = User::factory()->explorer()->create();

    $this->actingAs($explorer)
        ->get(route('signals.show', $need))
        ->assertOk();

    $this->actingAs($explorer)
        ->post(route('signals.like', $need))
        ->assertOk()
        ->assertJson(['liked' => true]);

    $this->actingAs($explorer)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'title' => 'Need a contractor',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('type');
});

test('explorers can reply to business posts as themselves', function () {
    $business = User::factory()->create(['business_name' => 'Eddy Labs']);
    $need = Signal::factory()->need()->for($business)->create();
    $explorer = User::factory()->explorer()->create([
        'full_name' => 'Sam',
        'last_name' => 'River',
    ]);

    $this->actingAs($explorer)
        ->post(route('signals.store'), [
            'parent_id' => $need->public_id,
            'type' => SignalType::Need->value,
            'body' => 'I can help with this.',
        ])
        ->assertRedirect(route('signals.show', $need));

    $reply = Signal::query()->where('parent_id', $need->getKey())->first();

    expect($reply?->type)->toBe(SignalType::Drop)
        ->and($reply?->user->displayName())->toBe('Sam River');
});

test('talent can post opportunities but not needs', function () {
    $talent = User::factory()->talent()->create();

    $this->actingAs($talent)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'title' => 'Need staff',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('type');

    $this->actingAs($talent)
        ->post(route('signals.store'), [
            'type' => SignalType::Opportunity->value,
            'title' => 'Brand sprint available',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('signals', [
        'user_id' => $talent->id,
        'type' => SignalType::Opportunity->value,
        'title' => 'Brand sprint available',
    ]);
});
