<?php

use App\Models\Signal;
use App\Models\User;

test('guests cannot store a live location', function () {
    $this->postJson(route('location.store'), [
        'latitude' => 28.5383,
        'longitude' => -81.3792,
    ])->assertUnauthorized();
});

test('authenticated users can store their live location', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 28.5383355,
            'longitude' => -81.3792365,
        ])
        ->assertOk()
        ->assertJsonPath('latitude', 28.5383355)
        ->assertJsonPath('longitude', -81.3792365);

    $user->refresh();

    expect($user->latitude)->toEqual(28.5383355)
        ->and($user->longitude)->toEqual(-81.3792365)
        ->and($user->location_updated_at)->not->toBeNull();
});

test('live location coordinates must be valid', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 120,
            'longitude' => -81.3792,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('latitude');
});

test('a stored live location is used on a later visit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 28.54,
            'longitude' => -81.38,
        ])
        ->assertOk();

    $this->flushSession();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLatitude', 28.54)
            ->where('viewerLongitude', -81.38)
            ->where('viewerLocation', 'Current location'));
});

test('the feed ranks nearby signals using the users stored location', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    $far = Signal::factory()->for($user)->need()->at(40.7128, -74.006, 'New York, NY')->create([
        'created_at' => now(),
    ]);
    $near = Signal::factory()->for($user)->need()->at(28.5383, -81.3792, 'Orlando, FL')->create([
        'created_at' => now()->subHour(),
    ]);
    $unlocated = Signal::factory()->for($user)->drop()->create([
        'created_at' => now()->addMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLatitude', 28.54)
            ->where('viewerLongitude', -81.38)
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 3)
                ->where('signals.data.0.id', $near->public_id)
                ->where('signals.data.1.id', $far->public_id)
                ->where('signals.data.2.id', $unlocated->public_id)));
});

test('users can pick a labeled viewing location', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 40.7128,
            'longitude' => -74.006,
            'label' => 'New York, NY',
            'manual' => true,
        ])
        ->assertOk()
        ->assertJsonPath('label', 'New York, NY')
        ->assertJsonPath('manual', true);

    $user->refresh();

    expect($user->location_label)->toBe('New York, NY')
        ->and($user->location_manual)->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLatitude', 40.7128)
            ->where('viewerLongitude', -74.006)
            ->where('viewerLocation', 'New York, NY')
            ->where('viewerLocationManual', true));
});

test('a picked location is not overwritten by a live gps ping', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_label' => 'Orlando, FL',
        'location_manual' => true,
        'location_updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 40.7128,
            'longitude' => -74.006,
        ])
        ->assertOk()
        ->assertJsonPath('latitude', 28.54)
        ->assertJsonPath('longitude', -81.38)
        ->assertJsonPath('label', 'Orlando, FL')
        ->assertJsonPath('manual', true);

    $user->refresh();

    expect($user->latitude)->toEqual(28.54)
        ->and($user->longitude)->toEqual(-81.38)
        ->and($user->location_label)->toBe('Orlando, FL')
        ->and($user->location_manual)->toBeTrue();
});

test('changing the viewing location reranks the feed', function () {
    $user = User::factory()->create();
    $orlando = Signal::factory()->for($user)->need()->at(28.5383, -81.3792, 'Orlando, FL')->create([
        'created_at' => now()->subHour(),
    ]);
    $nyc = Signal::factory()->for($user)->need()->at(40.7128, -74.006, 'New York, NY')->create([
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 28.54,
            'longitude' => -81.38,
            'label' => 'Orlando, FL',
            'manual' => true,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLocation', 'Orlando, FL')
            ->loadDeferredProps('feed', fn ($page) => $page
                ->where('signals.data.0.id', $orlando->public_id)
                ->where('signals.data.1.id', $nyc->public_id)));

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 40.7128,
            'longitude' => -74.006,
            'label' => 'New York, NY',
            'manual' => true,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLocation', 'New York, NY')
            ->loadDeferredProps('feed', fn ($page) => $page
                ->where('signals.data.0.id', $nyc->public_id)
                ->where('signals.data.1.id', $orlando->public_id)));
});
