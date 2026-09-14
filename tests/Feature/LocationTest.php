<?php

use App\Models\Signal;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests cannot store a live location', function () {
    $this->postJson(route('location.store'), [
        'latitude' => 28.5383,
        'longitude' => -81.3792,
    ])->assertUnauthorized();
});

test('authenticated users can store their live location', function () {
    config(['services.google.places_key' => null]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 28.5383355,
            'longitude' => -81.3792365,
        ])
        ->assertOk()
        ->assertJsonPath('latitude', 28.5383355)
        ->assertJsonPath('longitude', -81.3792365)
        ->assertJsonPath('label', '');

    $user->refresh();

    expect($user->latitude)->toEqual(28.5383355)
        ->and($user->longitude)->toEqual(-81.3792365)
        ->and($user->location_updated_at)->not->toBeNull();
});

test('live gps coordinates are shown as a real place name', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response([
        'status' => 'OK',
        'results' => [
            [
                'types' => ['locality', 'political'],
                'formatted_address' => 'Orlando, FL, USA',
                'address_components' => [
                    [
                        'long_name' => 'Orlando',
                        'short_name' => 'Orlando',
                        'types' => ['locality', 'political'],
                    ],
                    [
                        'long_name' => 'Florida',
                        'short_name' => 'FL',
                        'types' => ['administrative_area_level_1', 'political'],
                    ],
                    [
                        'long_name' => 'United States',
                        'short_name' => 'US',
                        'types' => ['country', 'political'],
                    ],
                ],
            ],
        ],
    ]));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 28.5383,
            'longitude' => -81.3792,
        ])
        ->assertOk()
        ->assertJsonPath('label', 'Orlando, FL');

    expect($user->fresh()->location_label)->toBe('Orlando, FL');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'maps.googleapis.com/maps/api/geocode/json'));
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
    config(['services.google.places_key' => null]);

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
            ->where('viewerLocation', ''));
});

test('the feed ranks nearby signals using the users stored location', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    Signal::factory()->for($user)->need()->at(40.7128, -74.006, 'New York, NY')->create([
        'created_at' => now(),
    ]);
    $near = Signal::factory()->for($user)->need()->at(28.5383, -81.3792, 'Orlando, FL')->create([
        'created_at' => now()->subHour(),
    ]);
    $withinRadius = Signal::factory()->for($user)->need()->at(27.9506, -82.4572, 'Tampa, FL')->create([
        'created_at' => now()->subMinutes(30),
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
                ->where('signals.data.0.id', $unlocated->public_id)
                ->where('signals.data.1.id', $withinRadius->public_id)
                ->where('signals.data.2.id', $near->public_id)));
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
    $miami = Signal::factory()->for($user)->need()->at(25.7617, -80.1918, 'Miami, FL')->create([
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
                ->has('signals.data', 2)
                ->where('signals.data.0.id', $orlando->public_id)
                ->where('signals.data.1.id', $miami->public_id)));

    $this->actingAs($user)
        ->postJson(route('location.store'), [
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'label' => 'Miami, FL',
            'manual' => true,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLocation', 'Miami, FL')
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 2)
                ->where('signals.data.0.id', $miami->public_id)
                ->where('signals.data.1.id', $orlando->public_id)));
});

test('the feed expands the search radius when nothing is nearby', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    Signal::factory()->for($user)->need()->at(40.7128, -74.006, 'New York, NY')->create();
    $within500 = Signal::factory()->for($user)->need()->at(25.7617, -80.1918, 'Miami, FL')->create([
        'created_at' => now()->subHour(),
    ]);
    $unlocated = Signal::factory()->for($user)->drop()->create([
        'created_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 2)
                ->where('signals.data.0.id', $unlocated->public_id)
                ->where('signals.data.1.id', $within500->public_id)));
});

test('the feed expands past 1000km in 250km steps until posts are found', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    $far = Signal::factory()->for($user)->need()->at(40.7128, -74.006, 'New York, NY')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 1)
                ->where('signals.data.0.id', $far->public_id)));
});

test('a discovered radius beyond 1000km becomes the new initial window', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    Signal::factory()->for($user)->count(11)->at(40.7128, -74.006, 'New York, NY')->create();
    $farther = Signal::factory()->for($user)->need()->at(51.5074, -0.1278, 'London, UK')->create([
        'created_at' => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 10)
                ->where('signals.last_page', 2)
                ->where('signals.data', fn ($signals) => collect($signals)->pluck('id')->doesntContain($farther->public_id))));

    $this->actingAs($user)
        ->get(route('dashboard', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 1)
                ->where('signals.current_page', 2)
                ->where('signals.data', fn ($signals) => collect($signals)->pluck('id')->doesntContain($farther->public_id))));
});

test('the feed paginates ten nearby signals at a time', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    Signal::factory()->for($user)->count(11)->at(28.5383, -81.3792, 'Orlando, FL')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 10)
                ->where('signals.per_page', 10)
                ->where('signals.last_page', 2)));

    $this->actingAs($user)
        ->get(route('dashboard', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 1)
                ->where('signals.current_page', 2)));
});

test('later pages continue into the next radius after nearby posts are exhausted', function () {
    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
        'location_updated_at' => now(),
    ]);

    Signal::factory()->for($user)->count(11)->at(28.5383, -81.3792, 'Orlando, FL')->create();
    $within500 = Signal::factory()->for($user)->need()->at(25.7617, -80.1918, 'Miami, FL')->create([
        'created_at' => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 10)
                ->where('signals.last_page', 2)
                ->where('signals.data', fn ($signals) => collect($signals)->pluck('id')->doesntContain($within500->public_id))));

    $this->actingAs($user)
        ->get(route('dashboard', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 2)
                ->where('signals.current_page', 2)
                ->where('signals.data.1.id', $within500->public_id)));
});
